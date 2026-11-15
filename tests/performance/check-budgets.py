#!/usr/bin/env python3
"""Check Lighthouse medians, regressions, and built-asset budgets."""

from __future__ import annotations

import argparse
import json
import statistics
import sys
from pathlib import Path
from typing import Any


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--results-dir", type=Path, required=True)
    parser.add_argument("--budgets", type=Path, required=True)
    parser.add_argument("--baseline-dir", type=Path)
    parser.add_argument("--assets-root", type=Path, default=Path("."))
    parser.add_argument("--report", type=Path)
    return parser.parse_args()


def median(values: list[float]) -> float:
    return statistics.median(values)


def load_reports(directory: Path) -> dict[tuple[str, str], list[dict[str, Any]]]:
    reports: dict[tuple[str, str], list[dict[str, Any]]] = {}
    for path in sorted(directory.glob("*.json")):
        parts = path.stem.rsplit("-", 2)
        if len(parts) != 3 or parts[2].isdigit() is False:
            continue

        page, mode, _run = parts
        reports.setdefault((page, mode), []).append(json.loads(path.read_text(encoding="utf-8")))

    return reports


def metrics(reports: list[dict[str, Any]]) -> dict[str, float]:
    return {
        "performance": median([report["categories"]["performance"]["score"] * 100 for report in reports]),
        "accessibility": median([report["categories"]["accessibility"]["score"] * 100 for report in reports]),
        "seo": median([report["categories"]["seo"]["score"] * 100 for report in reports]),
        "lcp_ms": median([report["audits"]["largest-contentful-paint"]["numericValue"] for report in reports]),
        "cls": median([report["audits"]["cumulative-layout-shift"]["numericValue"] for report in reports]),
        "tbt_ms": median([report["audits"]["total-blocking-time"]["numericValue"] for report in reports]),
        "transfer_bytes": median([report["audits"]["total-byte-weight"]["numericValue"] for report in reports]),
    }


def budget_for(config: dict[str, Any], page: str, mode: str) -> dict[str, float]:
    budget = dict(config["defaults"][mode])
    budget.update(config.get("pages", {}).get(page, {}).get(mode, {}))
    return budget


def check_metrics(config: dict[str, Any], current: dict[tuple[str, str], dict[str, float]]) -> list[str]:
    failures: list[str] = []
    for (page, mode), values in sorted(current.items()):
        budget = budget_for(config, page, mode)
        if values["performance"] < budget["performance_min"]:
            failures.append(f"{page}/{mode}: performance {values['performance']:.1f} < {budget['performance_min']}")
        if values["lcp_ms"] > budget["lcp_max_ms"]:
            failures.append(f"{page}/{mode}: LCP {values['lcp_ms']:.0f}ms > {budget['lcp_max_ms']}ms")
        if values["cls"] > budget["cls_max"]:
            failures.append(f"{page}/{mode}: CLS {values['cls']:.3f} > {budget['cls_max']}")
        if values["tbt_ms"] > budget["tbt_max_ms"]:
            failures.append(f"{page}/{mode}: TBT {values['tbt_ms']:.0f}ms > {budget['tbt_max_ms']}ms")
        if values["transfer_bytes"] > budget["transfer_max_bytes"]:
            failures.append(f"{page}/{mode}: transfer {values['transfer_bytes']:.0f}B > {budget['transfer_max_bytes']}B")

    return failures


def check_regressions(
    config: dict[str, Any],
    baseline: dict[tuple[str, str], dict[str, float]],
    current: dict[tuple[str, str], dict[str, float]],
) -> list[str]:
    tolerance = config["regression"]
    failures: list[str] = []
    for key, before in baseline.items():
        if key not in current:
            failures.append(f"{key[0]}/{key[1]}: missing after measurements")
            continue

        after = current[key]
        if after["performance"] < before["performance"] - tolerance["performance_score_tolerance"]:
            failures.append(f"{key[0]}/{key[1]}: performance regressed {before['performance']:.1f} -> {after['performance']:.1f}")
        lcp_limit = before["lcp_ms"] * (1 + tolerance["lcp_tolerance_ratio"]) + tolerance["lcp_tolerance_ms"]
        if after["lcp_ms"] > lcp_limit:
            failures.append(f"{key[0]}/{key[1]}: LCP regressed {before['lcp_ms']:.0f}ms -> {after['lcp_ms']:.0f}ms")
        if after["cls"] > before["cls"] + tolerance["cls_tolerance"]:
            failures.append(f"{key[0]}/{key[1]}: CLS regressed {before['cls']:.3f} -> {after['cls']:.3f}")

    return failures


def check_assets(config: dict[str, Any], root: Path) -> list[str]:
    failures: list[str] = []
    for pattern, maximum in config.get("assets", {}).items():
        matches = list(root.glob(pattern))
        if not matches:
            failures.append(f"asset budget has no matching files: {pattern}")
            continue
        for path in matches:
            if path.stat().st_size > maximum:
                failures.append(f"asset {path}: {path.stat().st_size}B > {maximum}B")

    return failures


def format_report(current: dict[tuple[str, str], dict[str, float]]) -> str:
    lines = ["| Page | Mode | Performance | LCP ms | CLS | TBT ms | Transfer bytes |", "| --- | --- | ---: | ---: | ---: | ---: | ---: |"]
    for (page, mode), values in sorted(current.items()):
        lines.append(f"| {page} | {mode} | {values['performance']:.1f} | {values['lcp_ms']:.0f} | {values['cls']:.3f} | {values['tbt_ms']:.0f} | {values['transfer_bytes']:.0f} |")
    return "\n".join(lines) + "\n"


def main() -> int:
    args = parse_args()
    config = json.loads(args.budgets.read_text(encoding="utf-8"))
    current_reports = load_reports(args.results_dir)
    current = {key: metrics(value) for key, value in current_reports.items() if len(value) >= 3}
    failures = check_metrics(config, current)
    failures.extend(check_assets(config, args.assets_root))

    if args.baseline_dir:
        baseline_reports = load_reports(args.baseline_dir)
        baseline = {key: metrics(value) for key, value in baseline_reports.items() if len(value) >= 3}
        failures.extend(check_regressions(config, baseline, current))

    report = format_report(current)
    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(report, encoding="utf-8")

    print(report, end="")
    if failures:
        print("Budget failures:", file=sys.stderr)
        print("\n".join(f"- {failure}" for failure in failures), file=sys.stderr)
        return 1

    print("Performance budgets passed.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

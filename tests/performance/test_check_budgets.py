import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
CHECKER = ROOT / "tests" / "performance" / "check-budgets.py"


def report(performance: int = 95, lcp: int = 1800, cls: float = 0.02) -> dict:
    return {
        "categories": {
            "performance": {"score": performance / 100},
            "accessibility": {"score": 0.95},
            "seo": {"score": 1},
        },
        "audits": {
            "largest-contentful-paint": {"numericValue": lcp},
            "cumulative-layout-shift": {"numericValue": cls},
            "total-blocking-time": {"numericValue": 100},
            "total-byte-weight": {"numericValue": 100000},
        },
    }


class PerformanceBudgetTest(unittest.TestCase):
    def run_checker(self, result: dict) -> subprocess.CompletedProcess[str]:
        with tempfile.TemporaryDirectory() as temporary_directory:
            directory = Path(temporary_directory)
            for run in range(1, 4):
                (directory / f"home-mobile-{run}.json").write_text(json.dumps(result), encoding="utf-8")

            return subprocess.run(
                [sys.executable, str(CHECKER), "--results-dir", str(directory), "--budgets", str(ROOT / "tests/performance/budgets.json")],
                cwd=ROOT,
                capture_output=True,
                text=True,
                check=False,
            )

    def test_passing_lighthouse_median_passes_budget_check(self) -> None:
        result = self.run_checker(report())

        self.assertEqual(result.returncode, 0, result.stderr)

    def test_failing_lighthouse_fixture_reports_actionable_budget_failures(self) -> None:
        result = self.run_checker(report(performance=56, lcp=10808, cls=0.2))

        self.assertNotEqual(result.returncode, 0)
        self.assertIn("performance 56.0 < 90", result.stderr)
        self.assertIn("LCP 10808ms > 2500ms", result.stderr)
        self.assertIn("CLS 0.200 > 0.1", result.stderr)


if __name__ == "__main__":
    unittest.main()

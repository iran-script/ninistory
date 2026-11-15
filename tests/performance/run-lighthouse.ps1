param(
    [string] $BaseUrl = 'http://127.0.0.1:8123',
    [string] $OutputDirectory = '.e2e/lighthouse/latest',
    [int] $Runs = 3
)

$ErrorActionPreference = 'Stop'
$urls = [ordered]@{
    home = '/'
    catalog = '/products'
    category = '/categories/demo-girls'
    product = '/products/demo-girls-01'
    articles = '/articles'
}

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null

foreach ($page in $urls.GetEnumerator()) {
    foreach ($mode in @('mobile', 'desktop')) {
        foreach ($run in 1..$Runs) {
            $output = Join-Path $OutputDirectory "$($page.Key)-$mode-$run.json"
            $arguments = @(
                '--yes', 'lighthouse@13.6.0', "$BaseUrl$($page.Value)",
                '--quiet', '--output=json', "--output-path=$output",
                '--only-categories=performance,accessibility,seo',
                '--chrome-flags=--headless=new --no-sandbox'
            )

            if ($mode -eq 'desktop') {
                $arguments += '--preset=desktop'
            } else {
                $arguments += '--form-factor=mobile'
            }

            & npx.cmd @arguments
            if ($LASTEXITCODE -ne 0) {
                throw "Lighthouse failed for $($page.Key) $mode run $run"
            }
        }
    }
}

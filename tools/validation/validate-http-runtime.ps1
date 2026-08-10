[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$dockerfile = Get-Content -LiteralPath (Join-Path $repositoryRoot 'backend\laravel-core\Dockerfile') -Raw
$entrypoint = Get-Content -LiteralPath (Join-Path $repositoryRoot 'platform\docker\laravel-entrypoint.sh') -Raw
$fpmConfig = Get-Content -LiteralPath (Join-Path $repositoryRoot 'platform\docker\php-fpm-production.conf') -Raw
$compose = Get-Content -LiteralPath (Join-Path $repositoryRoot 'docker-compose.yml') -Raw
$httpNginx = Get-Content -LiteralPath (Join-Path $repositoryRoot 'platform\gateway\backend-http\nginx.conf') -Raw
$reverbNginx = Get-Content -LiteralPath (Join-Path $repositoryRoot 'platform\gateway\nginx\nginx.conf') -Raw
$backendBlock = [regex]::Match($compose, '(?ms)^  backend:\r?\n.*?(?=^  [a-zA-Z0-9][a-zA-Z0-9-]*:\r?\n|\z)').Value
$backendHttpBlock = [regex]::Match($compose, '(?ms)^  backend-http:\r?\n.*?(?=^  [a-zA-Z0-9][a-zA-Z0-9-]*:\r?\n|\z)').Value

function Assert-Matches {
    param(
        [Parameter(Mandatory)] [string] $Content,
        [Parameter(Mandatory)] [string] $Pattern,
        [Parameter(Mandatory)] [string] $Message
    )

    if ($Content -notmatch $Pattern) {
        throw $Message
    }
}

function Assert-DoesNotMatch {
    param(
        [Parameter(Mandatory)] [string] $Content,
        [Parameter(Mandatory)] [string] $Pattern,
        [Parameter(Mandatory)] [string] $Message
    )

    if ($Content -match $Pattern) {
        throw $Message
    }
}

Assert-Matches $dockerfile '(?m)^FROM php:8\.3-fpm' 'Laravel production image must use PHP-FPM.'
Assert-Matches $dockerfile '(?m)^EXPOSE 9000(?:\s|$)' 'Laravel image must expose the internal FastCGI port.'
Assert-Matches $dockerfile 'php-fpm-production\.conf /usr/local/etc/php-fpm\.d/zz-youyaku\.conf' 'Laravel image must install the hardened FPM pool override.'
Assert-DoesNotMatch $dockerfile '(?m)^FROM php:8\.3-cli' 'The CLI-only production base image is forbidden.'

Assert-Matches $entrypoint '(?m)^exec php-fpm -F\s*$' 'Backend entrypoint must end in foreground PHP-FPM.'
Assert-DoesNotMatch $entrypoint 'php\s+-S|artisan\s+serve' 'PHP development servers are forbidden in the production entrypoint.'
Assert-Matches $entrypoint 'find -P "\$target_path" -xdev -type l' 'Writable-tree normalization must reject nested symlinks.'
Assert-Matches $entrypoint 'chown -R --no-dereference www-data:www-data' 'Persistent Laravel writable trees must be normalized without symlink dereference.'
Assert-Matches $fpmConfig '(?m)^access\.log\s*=\s*/dev/null\s*$' 'FPM request logging must be disabled so signed query tokens cannot leak.'

Assert-Matches $backendBlock 'php -r .*?fsockopen.*?9000' 'Backend healthcheck must probe the private FPM listener.'
Assert-Matches $backendHttpBlock '(?s)BACKEND_HOST_PORT.*?:8080.*?youyaku_storage:/var/www/html/storage:ro' 'backend-http must own the legacy host port and mount shared storage read-only.'
Assert-DoesNotMatch $backendBlock '(?m)^\s+ports:' 'The private FPM service must not publish any host port.'
Assert-Matches $compose 'BACKEND_PROXY_URL: http://backend-http:8080' 'Next builds must proxy through the HTTP frontend.'

foreach ($nginxConfig in @($httpNginx, $reverbNginx)) {
    $logFormat = [regex]::Match($nginxConfig, '(?ms)log_format\s+[^;]+;').Value
    if ([string]::IsNullOrWhiteSpace($logFormat)) {
        throw 'Every Nginx runtime must declare an explicit access-log format.'
    }

    Assert-Matches $logFormat '\$uri\b' 'Nginx access logs must record the normalized queryless $uri.'
    Assert-DoesNotMatch $logFormat '\$(?:request|request_uri|args|query_string)\b' 'Nginx access logs must never include request lines or query-bearing variables.'
}

Assert-Matches $httpNginx 'include /etc/nginx/fastcgi_params;' 'Dynamic requests must use standard FastCGI parameters.'
Assert-Matches $httpNginx 'fastcgi_param QUERY_STRING \$query_string;' 'Signed URL queries must still reach Laravel over FastCGI.'
Assert-Matches $httpNginx 'fastcgi_param REQUEST_URI \$request_uri;' 'Laravel must receive the original request URI for signature validation.'
Assert-Matches $httpNginx 'fastcgi_param HTTP_AUTHORIZATION \$http_authorization;' 'Bearer authorization must be forwarded to Laravel.'
Assert-Matches $httpNginx 'fastcgi_param REMOTE_ADDR \$remote_addr;' 'FastCGI must use the immediate Nginx peer address.'
Assert-Matches $httpNginx 'fastcgi_param HTTP_X_FORWARDED_FOR \$remote_addr;' 'Untrusted client forwarding chains must be overwritten.'
Assert-DoesNotMatch $httpNginx 'HTTP_X_FORWARDED_FOR \$http_x_forwarded_for' 'Client-supplied X-Forwarded-For must never reach Laravel.'
Assert-Matches $httpNginx 'fastcgi_param HTTP_X_REQUEST_ID \$request_id;' 'Nginx must overwrite client request identifiers.'
Assert-Matches $httpNginx 'fastcgi_param HTTP_X_CORRELATION_ID \$request_id;' 'Nginx must overwrite client correlation identifiers.'
Assert-Matches $httpNginx 'fastcgi_pass laravel_fpm;' 'Dynamic requests must be forwarded to the private FPM upstream.'
Assert-Matches $httpNginx '(?m)^\s*error_log /dev/stderr ' 'Nginx error logs must remain on stderr.'
Assert-Matches $httpNginx '(?m)^\s*root /var/www/html/public;' 'Nginx must expose only Laravel public assets.'

Write-Output 'HTTP runtime static validation: PASS'

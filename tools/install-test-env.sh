#!/usr/bin/env bash
# Create only an explicitly disposable WordPress/WooCommerce test environment.
set -euo pipefail
: "${QPC_ALLOW_TEST_DB_RESET:?Set QPC_ALLOW_TEST_DB_RESET=1 for a disposable test database.}"
[[ "$QPC_ALLOW_TEST_DB_RESET" == 1 ]] || exit 1
export QPC_TEST_ROOT="${QPC_TEST_ROOT:-/tmp/qpc-tests-env}"
export QPC_TEST_DB_NAME="${QPC_TEST_DB_NAME:-qpc_tests}"
export QPC_TEST_DB_HOST="${QPC_TEST_DB_HOST:-127.0.0.1}"
export QPC_TEST_DB_USER="${QPC_TEST_DB_USER:-root}"
export QPC_TEST_DB_PASSWORD="${QPC_TEST_DB_PASSWORD:-}"
[[ "$QPC_TEST_DB_NAME" =~ ^qpc_[a-zA-Z0-9_]+$ ]] || { echo 'Test database must start qpc_ and contain only letters, digits and underscores.'; exit 1; }
qpc_wp_version="${QPC_WP_VERSION:-6.8.3}"
qpc_wc_version="${QPC_WC_VERSION:-10.0.4}"
[[ "$qpc_wp_version" =~ ^[0-9.]+$ && "$qpc_wc_version" =~ ^[0-9.]+$ ]] || exit 1
mkdir -p "$QPC_TEST_ROOT"
curl -fsSL "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${qpc_wp_version}.tar.gz" -o "$QPC_TEST_ROOT/develop.tar.gz"
tar -xzf "$QPC_TEST_ROOT/develop.tar.gz" -C "$QPC_TEST_ROOT"
curl -fsSL "https://wordpress.org/wordpress-${qpc_wp_version}.tar.gz" -o "$QPC_TEST_ROOT/wordpress.tar.gz"
tar -xzf "$QPC_TEST_ROOT/wordpress.tar.gz" -C "$QPC_TEST_ROOT"
export QPC_TEST_DEVELOP="$QPC_TEST_ROOT/wordpress-develop-$qpc_wp_version"
cp -R "$QPC_TEST_ROOT/wordpress/wp-includes/." "$QPC_TEST_DEVELOP/src/wp-includes/"
cp -R "$QPC_TEST_ROOT/wordpress/wp-admin/." "$QPC_TEST_DEVELOP/src/wp-admin/"
curl -fsSL "https://downloads.wordpress.org/plugin/woocommerce.${qpc_wc_version}.zip" -o "$QPC_TEST_ROOT/woocommerce.zip"
unzip -oq "$QPC_TEST_ROOT/woocommerce.zip" -d "$QPC_TEST_DEVELOP/src/wp-content/plugins"
MYSQL_PWD="$QPC_TEST_DB_PASSWORD" mysql --host="$QPC_TEST_DB_HOST" --user="$QPC_TEST_DB_USER" -e "CREATE DATABASE IF NOT EXISTS \`$QPC_TEST_DB_NAME\`;"
php <<'PHP'
<?php
$config = "<?php\n";
foreach (array('DB_NAME'=>'QPC_TEST_DB_NAME','DB_USER'=>'QPC_TEST_DB_USER','DB_PASSWORD'=>'QPC_TEST_DB_PASSWORD','DB_HOST'=>'QPC_TEST_DB_HOST') as $constant=>$env) {
    $config .= 'define(' . var_export($constant,true) . ',' . var_export(getenv($env),true) . ");\n";
}
$config .= 'define("ABSPATH",' . var_export(getenv('QPC_TEST_DEVELOP').'/src/',true) . ");\n";
$config .= <<<'CONFIG'
define('DB_CHARSET','utf8');
define('DB_COLLATE','');
define('WP_TESTS_DOMAIN','productclock.test');
define('WP_TESTS_EMAIL','admin@example.org');
define('WP_TESTS_TITLE','ProductClock isolated tests');
define('WP_PHP_BINARY','php');
define('WP_TESTS_FORCE_KNOWN_BUGS',false);
define('WP_DEBUG',true);
$table_prefix='qpctest_';
CONFIG;
file_put_contents(getenv('QPC_TEST_DEVELOP').'/wp-tests-config.php',$config);
PHP
printf 'Test environment ready. Set WP_TESTS_DIR=%s/tests/phpunit\n' "$QPC_TEST_DEVELOP"

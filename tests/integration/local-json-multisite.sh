#!/usr/bin/env bash

set -euo pipefail

SCF_INTEGRATION_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$SCF_INTEGRATION_ROOT"

SCF_WP_ENV_HOME="$(mktemp -d "${TMPDIR:-/tmp}/scf-local-json-multisite.XXXXXX")"
SCF_WP_ENV_PROJECT="${SCF_WP_ENV_HOME}/project"
SCF_WP_ENV_DESTROY_REQUIRED=false

wp_env() {
	(
		cd "$SCF_WP_ENV_PROJECT"
		"${SCF_INTEGRATION_ROOT}/node_modules/.bin/wp-env" "$@"
	)
}

cleanup() {
	local integration_status=$?
	local destroy_status=0

	trap - EXIT INT TERM
	if [[ "$SCF_WP_ENV_DESTROY_REQUIRED" == true ]]; then
		if (( integration_status != 0 )); then
			echo "Integration failed with status ${integration_status}; dumping wp-env logs before destroying the environment."
			wp_env logs all --no-watch || true
		fi

		printf 'y\n' | wp_env destroy || destroy_status=$?
	fi

	if [[ -n "$SCF_WP_ENV_HOME" && "$SCF_WP_ENV_HOME" == *"/scf-local-json-multisite."* ]]; then
		rm -rf -- "$SCF_WP_ENV_HOME"
	fi

	if (( integration_status != 0 )); then
		exit "$integration_status"
	fi

	exit "$destroy_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

mkdir "$SCF_WP_ENV_PROJECT"

node -e '
	const fs = require( "node:fs" );
	const config = {
		$schema: "https://schemas.wp.org/trunk/wp-env.json",
		multisite: true,
		plugins: [ process.argv[ 2 ] ],
	};
	fs.writeFileSync( process.argv[ 1 ], `${ JSON.stringify( config, null, 2 ) }\n` );
' "${SCF_WP_ENV_PROJECT}/.wp-env.json" "$SCF_INTEGRATION_ROOT"

export WP_ENV_HOME="$SCF_WP_ENV_HOME"
export WP_ENV_MULTISITE=1

read -r WP_ENV_PORT WP_ENV_TESTS_PORT < <(
	node -e '
		const net = require( "node:net" );
		const servers = [ net.createServer(), net.createServer() ];
		Promise.all(
			servers.map(
				( server ) =>
					new Promise( ( resolve, reject ) => {
						server.once( "error", reject );
						server.listen( 0, "127.0.0.1", () => resolve( server.address().port ) );
					} )
			)
		).then( ( ports ) => {
			process.stdout.write( `${ ports[ 0 ] } ${ ports[ 1 ] }\n` );
			servers.forEach( ( server ) => server.close() );
		} );
	'
)
export WP_ENV_PORT
export WP_ENV_TESTS_PORT

wp_cli() {
	wp_env run cli wp "$@"
}

run_phase() {
	local site_url=$1
	local user=$2
	local phase=$3

	echo "Running Local JSON multisite phase: $phase"
	if [[ -n "$user" ]]; then
		wp_cli --url="$site_url" --user="$user" eval-file "$SCF_SCENARIO_FILE" "$phase"
	else
		wp_cli --url="$site_url" eval-file "$SCF_SCENARIO_FILE" "$phase"
	fi
}

SCF_PLUGIN_SLUG="$(basename "$SCF_INTEGRATION_ROOT")"
SCF_SCENARIO_FILE="wp-content/plugins/${SCF_PLUGIN_SLUG}/tests/integration/local-json-multisite.php"
SCF_SITE_A_URL="http://localhost:${WP_ENV_PORT}"
SCF_SITE_B_URL="${SCF_SITE_A_URL}/site-b/"
SCF_SITE_ADMIN="scfsiteaadmin"

echo "Checking the disposable wp-env status before start"
wp_env status || true

echo "Starting disposable multisite wp-env on dynamically allocated ports"
SCF_WP_ENV_DESTROY_REQUIRED=true
wp_env start

run_phase "$SCF_SITE_A_URL" "" setup

wp_cli --url="$SCF_SITE_A_URL" site create \
	--slug=site-b \
	--title="SCF Local JSON Site B" \
	--email=site-b@example.test

wp_cli --url="$SCF_SITE_A_URL" theme activate scf-local-json-integration
wp_cli --url="$SCF_SITE_B_URL" plugin activate "$SCF_PLUGIN_SLUG"
wp_cli --url="$SCF_SITE_B_URL" theme activate scf-local-json-integration

wp_cli --url="$SCF_SITE_A_URL" user create \
	"$SCF_SITE_ADMIN" \
	scf-site-a-admin@example.test \
	--role=administrator \
	--user_pass=scf-local-json-integration-only

run_phase "$SCF_SITE_A_URL" "$SCF_SITE_ADMIN" deny-create
run_phase "$SCF_SITE_A_URL" "$SCF_SITE_ADMIN" verify-database
run_phase "$SCF_SITE_A_URL" admin super-admin
run_phase "$SCF_SITE_A_URL" "$SCF_SITE_ADMIN" deny-overwrite-delete
run_phase "$SCF_SITE_A_URL" "" load-trusted
run_phase "$SCF_SITE_B_URL" "" load-trusted
run_phase "$SCF_SITE_A_URL" "$SCF_SITE_ADMIN" uploads-opt-in
run_phase "$SCF_SITE_A_URL" "$SCF_SITE_ADMIN" deny-other-site-uploads
run_phase "$SCF_SITE_A_URL" "" user-zero

echo "Local JSON multisite integration passed."

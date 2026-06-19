@extends('layouts/layoutHelpSimple')

@section('title', __('WordPress MCP in Cursor'))

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/prism/prism.css') }}" />
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/prism/prism.js') }}"></script>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h4 class="card-title mb-0">{{ __('WordPress MCP in Cursor') }}</h4>
                <a href="{{ route('help.environment-variables') }}" class="btn btn-sm btn-label-secondary">{{ __('← Configuraciones') }}</a>
            </div>
            <div class="card-body">
                <p class="lead">{{ __('Connect Cursor to your WordPress site using the Model Context Protocol (MCP). Cursor talks to WordPress through the official MCP Adapter plugin and an Application Password.') }}</p>

                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-info-circle me-2"></i>{{ __('What you need') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('A WordPress site with HTTPS (or a local .test domain).') }}</li>
                        <li>{{ __('The') }} <strong>MCP Adapter</strong> {{ __('plugin installed and active.') }}</li>
                        <li>{{ __('A WordPress user with administrator permissions.') }}</li>
                        <li>{{ __('An Application Password (not your login password).') }}</li>
                        <li>{{ __('Cursor with') }} <code>~/.cursor/mcp.json</code> {{ __('configured.') }}</li>
                    </ul>
                </div>

                <h5 class="mt-4">{{ __('1. Install the MCP Adapter plugin in WordPress') }}</h5>
                <ol>
                    <li>{{ __('Download the latest release from') }} <a href="https://github.com/WordPress/mcp-adapter/releases" target="_blank" rel="noopener noreferrer">WordPress/mcp-adapter</a>.</li>
                    <li>{{ __('In wp-admin go to') }} <strong>{{ __('Plugins') }}</strong> → <strong>{{ __('Add New') }}</strong> → <strong>{{ __('Upload Plugin') }}</strong>.</li>
                    <li>{{ __('Upload the ZIP and activate the plugin.') }}</li>
                    <li>{{ __('Go to') }} <strong>{{ __('Settings') }}</strong> → <strong>{{ __('Permalinks') }}</strong> → {{ __('click Save (flushes REST routes).') }}</li>
                </ol>
                <p class="mb-0">{{ __('Verify the endpoint responds with JSON (not the site homepage):') }}</p>
<pre class="language-text mt-2 mb-4"><code>https://your-site.test/wp-json/mcp/mcp-adapter-default-server</code></pre>

                <h5 class="mt-4">{{ __('2. Create an Application Password') }}</h5>
                <ol>
                    <li>{{ __('Log in to WordPress as an administrator.') }}</li>
                    <li>{{ __('Go to') }} <strong>{{ __('Users') }}</strong> → <strong>{{ __('Profile') }}</strong> ({{ __('or edit the target user') }}).</li>
                    <li>{{ __('Scroll to') }} <strong>{{ __('Application Passwords') }}</strong>.</li>
                    <li>{{ __('Name it (e.g. "Cursor MCP") and click') }} <strong>{{ __('Add New Application Password') }}</strong>.</li>
                    <li>{{ __('Copy the generated password immediately. It looks like') }} <code>xxxx xxxx xxxx xxxx xxxx xxxx</code>. {{ __('You cannot view it again.') }}</li>
                </ol>
                <div class="alert alert-warning">
                    <p class="mb-0"><strong>{{ __('Important:') }}</strong> {{ __('WP_API_PASSWORD in Cursor must be this Application Password, not your WordPress login password.') }}</p>
                </div>

                <h5 class="mt-4">{{ __('3. Configure Cursor (~/.cursor/mcp.json)') }}</h5>
                <p>{{ __('Add a') }} <code>wordpress</code> {{ __('entry inside') }} <code>mcpServers</code>:</p>
<pre class="language-json mb-4"><code>{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://your-site.test/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "your-wordpress-username",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}</code></pre>

                <h5 class="mt-4">{{ __('Environment variables explained') }}</h5>
                <table class="table table-bordered mb-4">
                    <thead>
                        <tr>
                            <th>{{ __('Variable') }}</th>
                            <th>{{ __('Description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>WP_API_URL</code></td>
                            <td>{{ __('Your site URL plus') }} <code>/wp-json/mcp/mcp-adapter-default-server</code>. {{ __('Replace') }} <code>your-site.test</code> {{ __('with your real domain.') }}</td>
                        </tr>
                        <tr>
                            <td><code>WP_API_USERNAME</code></td>
                            <td>{{ __('WordPress login username (e.g.') }} <code>root</code>).</td>
                        </tr>
                        <tr>
                            <td><code>WP_API_PASSWORD</code></td>
                            <td>{{ __('The Application Password from step 2.') }}</td>
                        </tr>
                        <tr>
                            <td><code>OAUTH_ENABLED</code></td>
                            <td>{{ __('Set to') }} <code>false</code> {{ __('when using username + Application Password.') }}</td>
                        </tr>
                    </tbody>
                </table>

                <h5 class="mt-4">{{ __('4. Restart Cursor') }}</h5>
                <p>{{ __('Reload MCP servers or restart Cursor. The WordPress server should appear as connected in MCP settings.') }}</p>

                <h5 class="mt-4">{{ __('Reset WordPress login password via database (developers)') }}</h5>
                <p>{{ __('If you lost the admin login password, generate a hash with PHP (from your WordPress root):') }}</p>
<pre class="language-bash mb-2"><code>php -r "require '/path/to/wordpress/wp-load.php'; echo wp_hash_password('YourNewPassword');"</code></pre>
                <p>{{ __('Then run in MySQL:') }}</p>
<pre class="language-sql mb-4"><code>UPDATE wp_users
SET user_pass = 'PASTE_HASH_HERE'
WHERE user_login = 'your-username';</code></pre>
                <p class="text-muted mb-0">{{ __('Do not store plain-text passwords in user_pass. This resets wp-admin login only; it does not replace the Application Password used by MCP.') }}</p>

                <div class="alert alert-warning mt-4">
                    <h6 class="alert-heading mb-2"><i class="ti ti-alert-triangle me-2"></i>{{ __('Troubleshooting') }}</h6>
                    <ul class="mb-0">
                        <li>{{ __('Endpoint returns HTML instead of JSON: install/activate MCP Adapter and flush permalinks.') }}</li>
                        <li>{{ __('401 Unauthorized: use an Application Password, not the login password.') }}</li>
                        <li>{{ __('404 on /wp-json/mcp/...: plugin missing or permalinks not saved.') }}</li>
                        <li>{{ __('Cursor shows disconnected: check WP_API_URL, restart Cursor, and confirm the site is reachable.') }}</li>
                    </ul>
                </div>

                <div class="mt-4">
                    <a href="{{ route('help.index') }}#cursor-mcp-setup" class="btn btn-label-primary me-2">{{ __('← Cursor MCP (Idoneo)') }}</a>
                    <a href="{{ route('help.woocommerce-configuration') }}" class="btn btn-label-success">{{ __('WooCommerce configuration') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

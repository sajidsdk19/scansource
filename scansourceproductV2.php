<?php
/**
 * Plugin Name: ScanSource API Plugin
 * Description: Fetches product details from ScanSource API and allows dynamic configuration via the WordPress admin panel.
 * Version: 1.1
 * Author: Sajid Khan
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class ScanSourceAPIPlugin {
    private $option_name = 'scansource_api_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_fetch_scansource_data', [$this, 'fetch_scansource_data']);
    }

    // 🔹 Add Menu in Admin Panel
    public function add_admin_page() {
        add_menu_page(
            'ScanSource API', 
            'ScanSource API', 
            'manage_options', 
            'scansource-api', 
            [$this, 'admin_page_content'], 
            'dashicons-cloud', 
            100
        );
    }

    // 🔹 Register Settings
    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
        add_settings_section('scansource_api_section', 'API Configuration', null, $this->option_name);

        $fields = [
            'authToken' => 'Auth Token',
            'subscriptionKey' => 'Subscription Key',
            'clientID' => 'Client ID',
            'clientSecret' => 'Client Secret'
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key, 
                $label, 
                function () use ($key) {
                    $options = get_option($this->option_name);
                    echo "<input type='text' name='{$this->option_name}[{$key}]' value='" . esc_attr($options[$key] ?? '') . "' class='regular-text'>";
                },
                $this->option_name,
                'scansource_api_section'
            );
        }
    }

    // 🔹 Admin Page Content
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1>ScanSource API Configuration</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections($this->option_name);
                submit_button();
                ?>
            </form>

            <h2>Fetch Product Details</h2>
            <button id="fetch-scansource-data" class="button button-primary">Fetch Data</button>
            <div id="scansource-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd;"></div>
        </div>

        <script type="text/javascript">
            document.getElementById('fetch-scansource-data').addEventListener('click', function() {
                let resultDiv = document.getElementById('scansource-result');
                resultDiv.innerHTML = "<strong>Fetching data...</strong>";

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=fetch_scansource_data'
                })
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = `<pre>Status: ${data.status}\nResponse: ${JSON.stringify(data.response, null, 2)}</pre>`;
                })
                .catch(error => {
                    resultDiv.innerHTML = "<strong>Error fetching data.</strong>";
                });
            });
        </script>
        <?php
    }

    // 🔹 Fetch API Data
    public function fetch_scansource_data() {
        $options = get_option($this->option_name);

        $authToken = $options['authToken'] ?? '';
        $subscriptionKey = $options['subscriptionKey'] ?? '';
        $clientID = $options['clientID'] ?? '';
        $clientSecret = $options['clientSecret'] ?? '';

        if (!$authToken || !$subscriptionKey || !$clientID || !$clientSecret) {
            wp_send_json(["error" => "Missing API credentials"], 400);
        }

        $apiURL = "https://api.scansource.com/scsc/product/v2/detail";
        $customerNumber = "1000051905";
        $itemNumber = "100";
        $partNumberType = "2";
        $region = "1";

        $url = "{$apiURL}?customerNumber={$customerNumber}&itemNumber={$itemNumber}&partNumberType={$partNumberType}&region={$region}";

        $headers = [
            "Authorization: Bearer {$authToken}",
            "Ocp-Apim-Subscription-Key: {$subscriptionKey}",
            "Client_ID: {$clientID}",
            "Client_Secret: {$clientSecret}",
            "Content-Type: application/json",
            "Cache-Control: no-cache",  // Disables caching
            "Pragma: no-cache"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true); // Ensures new connection

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        wp_send_json([
            "status" => $httpCode,
            "response" => json_decode($response, true)
        ]);
    }
}

// Initialize Plugin
new ScanSourceAPIPlugin();

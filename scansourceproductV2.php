<?php
/**
 * Plugin Name: ScanSource Search API Plugin
 * Description: Fetches product search results from ScanSource API and imports them as WooCommerce products with unique sequential IDs.
 * Version: 1.12
 * Author: xAI Assistant
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

class ScanSourceSearchAPIPlugin {
    private $option_name = 'scansource_search_api_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_fetch_scansource_search_data', [$this, 'fetch_scansource_search_data']);
        add_action('wp_ajax_import_scansource_products', [$this, 'import_scansource_products']);
    }

    // Add Admin Menu
    public function add_admin_page() {
        add_menu_page(
            'ScanSource Search API',
            'ScanSource Search',
            'manage_options',
            'scansource-search-api',
            [$this, 'admin_page_content'],
            'dashicons-search',
            101
        );
    }

    // Register Settings
    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
        add_settings_section('scansource_search_section', 'API Configuration', null, $this->option_name);

        $fields = [
            'authToken' => 'Authorization Token',
            'subscriptionKey' => 'Subscription Key',
            'customerNumber' => 'Customer Number',
            'itemNumber' => 'Item Number',
            'partNumberType' => 'Part Number Type',
            'manufacturer' => 'Manufacturer',
            'catalogName' => 'Catalog Name',
            'categoryPath' => 'Category Path',
            'includeObsolete' => 'Include Obsolete (true/false)',
            'searchText' => 'Search Text',
            'useAndOperator' => 'Use AND Operator (true/false)',
            'region' => 'Region',
            'page' => 'Page',
            'pageSize' => 'Page Size'
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                function () use ($key) {
                    $options = get_option($this->option_name);
                    $default_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImtpZCI6IkNOdjBPSTNSd3FsSEZFVm5hb01Bc2hDSDJYRSJ9.eyJhdWQiOiJhMmVjY2RiZC1iMjA4LTRlNzctYTgyZi1kZGRkMTgxNDMwOWEiLCJpc3MiOiJodHRwczovL2xvZ2luLm1pY3Jvc29mdG9ubGluZS5jb20vY2U5ODYxMDEtMGM1Mi00NjkzLWEzODMtOTg2OWU0ZWFjNDhmL3YyLjAiLCJpYXQiOjE3NDUwMTYzMTksIm5iZiI6MTc0NTAxNjMxOSwiZXhwIjoxNzQ1MDIwMjE5LCJhaW8iOiJBU1FBMi84WkFBQUFlOWZDS1VmbnVtMUxoWDJ0RlFrZmRwSjlYL1p0dGNDUzB2RURidU93dzRBPSIsImF6cCI6IjJmM2YxNDYzLTQxNjEtNGY3NS1iOTlkLWM5ZDhjZmMyYjc0MSIsImF6cGFjciI6IjEiLCJvaWQiOiJhNDJjOTlmZC01OGJjLTQ5ZjAtOGYyMC02ZTQ0NDRhMGIwZjAiLCJyaCI6IjEuQVJVQUFXR1l6bElNazBhamc1aHA1T3JFajczTjdLSUlzbmRPcUNfZDNSZ1VNSm9WQUFBVkFBLiIsInJvbGVzIjpbImFwcC5hY2Nlc3MiXSwic3ViIjoiYTQyYzk5ZmQtNThiYy00OWYwLThmMjAtNmU0NDQ0YTBiMGYwIiwidGlkIjoiY2U5ODYxMDEtMGM1Mi00NjkzLWEzODMtOTg2OWU0ZWFjNDhmIiwidXRpIjoiX1V1UlBuQ3o2a0NJallDdnBVMExBQSIsInZlciI6IjIuMCJ9.IegRaaG_vmGyE-JLh0EztdW1IcBzXlx8Dj396texq7Pael43_n1UG4Sb3CqEvQOXJW7wzaOywLp_fayl1lFnV2lPIrpTTU4A8rTnM38ZmY79M4b6Vz94VaCdNaHrVMH5BPN8Tf_e4OPPxoA-uKY-SLOysqWR3X3YBGoA2tHWwqrKijsLoV_ddg5hJogKEGtmA0Nl1DPhzMHAHxcZaf8mXNYCuyfX9MnUOFH9RAcGQJ7cXRmeDQGHVoBxTUxrytq39kBFWLu-2l2lkrXLCf9A48kc9giNxCt5i95JmKvyw4-zgMagzpf8kQ0AzbiSJWktvkoIqwByUwwI_NeqQdFLnA';
                    $default_subkey = '1cde05a8-3a7a-47ea-b5f3-09a5375037ff';
                    $value = $options[$key] ?? ($key === 'customerNumber' ? '1000051905' : ($key === 'authToken' ? $default_token : ($key === 'subscriptionKey' ? $default_subkey : '')));
                    echo "<input type='text' name='{$this->option_name}[{$key}]' value='" . esc_attr($value) . "' class='regular-text'>";
                },
                $this->option_name,
                'scansource_search_section'
            );
        }
    }

    // Admin Page Content
    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1>ScanSource Search API Configuration</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections($this->option_name);
                submit_button();
                ?>
            </form>

            <h2>Search Products</h2>
            <button id="fetch-scansource-search" class="button button-primary">Fetch Search Results</button>
            <button id="import-scansource-products" class="button button-secondary" style="margin-left: 10px;" disabled>Import as WooCommerce Products</button>
            <div id="scansource-search-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f5f5f5;"></div>
        </div>

        <script type="text/javascript">
            let lastFetchedData = null;

            document.getElementById('fetch-scansource-search').addEventListener('click', function() {
                let resultDiv = document.getElementById('scansource-search-result');
                let importButton = document.getElementById('import-scansource-products');
                resultDiv.innerHTML = "<strong>Fetching search results...</strong>";
                importButton.disabled = true;

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=fetch_scansource_search_data'
                })
                .then(response => response.json())
                .then(data => {
                    lastFetchedData = data;
                    const jsonString = JSON.stringify(data, null, 2);
                    resultDiv.innerHTML = `<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">${jsonString}</pre>`;
                    if (data.status === 200 && data.data && Array.isArray(data.data)) {
                        importButton.disabled = false;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `<pre style="color: red;">Error fetching search results: ${error.message}</pre>`;
                });
            });

            document.getElementById('import-scansource-products').addEventListener('click', function() {
                let resultDiv = document.getElementById('scansource-search-result');
                resultDiv.innerHTML = "<strong>Importing products...</strong>";

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=import_scansource_products&data=' + encodeURIComponent(JSON.stringify(lastFetchedData))
                })
                .then(response => response.json())
                .then(result => {
                    resultDiv.innerHTML = `<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">${JSON.stringify(result, null, 2)}</pre>`;
                })
                .catch(error => {
                    resultDiv.innerHTML = `<pre style="color: red;">Error importing products: ${error.message}</pre>`;
                });
            });
        </script>
        <?php
    }

    // Fetch Search Data
    public function fetch_scansource_search_data() {
        $options = get_option($this->option_name);

        $authToken = $options['authToken'] ?? '';
        $subscriptionKey = $options['subscriptionKey'] ?? '';
        $customerNumber = $options['customerNumber'] ?? '1000051905';
        $itemNumber = $options['itemNumber'] ?? '';
        $partNumberType = $options['partNumberType'] ?? '';
        $manufacturer = $options['manufacturer'] ?? '';
        $catalogName = $options['catalogName'] ?? '';
        $categoryPath = $options['categoryPath'] ?? '';
        $includeObsolete = $options['includeObsolete'] ?? '';
        $searchText = $options['searchText'] ?? '';
        $useAndOperator = $options['useAndOperator'] ?? '';
        $region = $options['region'] ?? '';
        $page = $options['page'] ?? '';
        $pageSize = $options['pageSize'] ?? '';

        if (!$authToken || !$subscriptionKey || !$customerNumber) {
            wp_send_json([
                "status" => 400,
                "error" => "Missing required API credentials (Authorization Token, Subscription Key, or Customer Number)"
            ], 400);
        }

        $apiURL = "https://api.scansource.com/scsc/product/v2/search";
        $params = array_filter([
            'customerNumber' => $customerNumber,
            'itemNumber' => $itemNumber,
            'partNumberType' => $partNumberType,
            'manufacturer' => $manufacturer,
            'catalogName' => $catalogName,
            'categoryPath' => $categoryPath,
            'includeObsolete' => $includeObsolete,
            'searchText' => $searchText,
            'useAndOperator' => $useAndOperator,
            'region' => $region,
            'page' => $page,
            'pageSize' => $pageSize
        ]);
        $url = $apiURL . '?' . http_build_query($params);

        $headers = [
            "Authorization: Bearer {$authToken}",
            "Ocp-Apim-Subscription-Key: {$subscriptionKey}",
            "Cache-Control: no-cache",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            fclose($verbose);
            curl_close($ch);
            wp_send_json([
                "status" => 500,
                "error" => "cURL error: " . $error,
                "debug" => $verboseLog,
                "request_url" => $url,
                "request_headers" => $headers
            ], 500);
        }

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        fclose($verbose);
        curl_close($ch);

        $response_data = json_decode($response, true);
        if ($response_data === null) {
            wp_send_json([
                "status" => $httpCode,
                "raw_response" => $response,
                "debug" => $verboseLog,
                "request_url" => $url,
                "request_headers" => $headers,
                "note" => "Response could not be parsed as JSON"
            ]);
        } else {
            wp_send_json([
                "status" => $httpCode,
                "data" => $response_data,
                "debug" => $verboseLog,
                "request_url" => $url,
                "request_headers" => $headers
            ]);
        }
    }

    // Import Products to WooCommerce
    public function import_scansource_products() {
        // Start output buffering to catch any unexpected output
        ob_start();

        // Ensure WooCommerce is active
        if (!class_exists('WC_Product')) {
            ob_clean();
            wp_send_json([
                "status" => 500,
                "error" => "WooCommerce is not installed or activated",
                "imported" => [],
                "errors" => []
            ], 500);
            return;
        }

        // Validate POST data
        if (!isset($_POST['data']) || empty($_POST['data'])) {
            ob_clean();
            wp_send_json([
                "status" => 400,
                "error" => "No product data provided",
                "imported" => [],
                "errors" => []
            ], 400);
            return;
        }

        // Decode JSON data safely
        $raw_data = stripslashes($_POST['data']);
        $data = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !$data || !isset($data['data']) || !is_array($data['data'])) {
            ob_clean();
            wp_send_json([
                "status" => 400,
                "error" => "Invalid JSON data: " . json_last_error_msg() . " (Raw data: " . substr($raw_data, 0, 100) . "...)",
                "imported" => [],
                "errors" => []
            ], 400);
            return;
        }

        $imported = [];
        $errors = [];

        foreach ($data['data'] as $index => $product_data) {
            try {
                // Check if product exists by SKU (itemNumber)
                $sku = isset($product_data['itemNumber']) ? sanitize_text_field($product_data['itemNumber']) : '';
                $existing_product_id = $sku ? wc_get_product_id_by_sku($sku) : 0;

                if ($existing_product_id && $sku) {
                    // Update existing product
                    $product = wc_get_product($existing_product_id);
                    $product_id = $existing_product_id; // Retain existing ID for updates
                } else {
                    // Create new product (let WordPress assign the ID)
                    $post_data = [
                        'post_type' => 'product',
                        'post_status' => 'publish',
                        'post_title' => isset($product_data['Description']) && !empty($product_data['Description']) ? sanitize_text_field($product_data['Description']) : 'Unnamed Product ' . ($index + 1)
                    ];
                    $product_id = wp_insert_post($post_data, true);
                    if (is_wp_error($product_id)) {
                        throw new Exception('Failed to create product post: ' . $product_id->get_error_message() . ' (Data: ' . json_encode($product_data) . ')');
                    }
                    $product = wc_get_product($product_id);
                }

                // Set basic product details
                $product->set_sku($sku);
                $product->set_regular_price(isset($product_data['price']) ? floatval($product_data['price']) : 0);
                $product->set_description(isset($product_data['Description']) ? wp_kses_post($product_data['Description']) : '');

                // Set "ProductFamily" as category (skip if null or empty)
                if (isset($product_data['ProductFamily']) && !empty($product_data['ProductFamily']) && is_string($product_data['ProductFamily'])) {
                    $category_name = sanitize_text_field($product_data['ProductFamily']);
                    $term = term_exists($category_name, 'product_cat');
                    if (!$term) {
                        $term = wp_insert_term($category_name, 'product_cat');
                    }
                    if (!is_wp_error($term) && isset($term['term_id'])) {
                        $product->set_category_ids([$term['term_id']]);
                    } else {
                        $errors[] = [
                            'itemNumber' => $sku,
                            'error' => "Failed to create or assign category '$category_name' (Data: " . json_encode($product_data) . ")"
                        ];
                    }
                }

                // Try downloading and setting featured image from ItemImage or ProductFamilyImage
                $image_urls = [];
                if (isset($product_data['ItemImage']) && !empty($product_data['ItemImage']) && filter_var($product_data['ItemImage'], FILTER_VALIDATE_URL)) {
                    $image_urls[] = esc_url_raw($product_data['ItemImage']);
                }
                if (isset($product_data['ProductFamilyImage']) && !empty($product_data['ProductFamilyImage']) && filter_var($product_data['ProductFamilyImage'], FILTER_VALIDATE_URL)) {
                    $image_urls[] = esc_url_raw($product_data['ProductFamilyImage']);
                }

                $attach_id = null;
                foreach ($image_urls as $image_url) {
                    $upload_dir = wp_upload_dir();
                    $image_data = @file_get_contents($image_url); // Suppress warnings
                    if ($image_data !== false) {
                        // Generate a filename with .jpg extension
                        $filename = sanitize_file_name(pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_FILENAME)) . '.jpg';
                        $file_path = $upload_dir['path'] . '/' . $filename;
                        file_put_contents($file_path, $image_data);

                        // Force JPEG MIME type
                        $attachment = [
                            'post_mime_type' => 'image/jpeg',
                            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                            'post_content' => '',
                            'post_status' => 'inherit'
                        ];

                        $attach_id = wp_insert_attachment($attachment, $file_path, $product_id);
                        if (!is_wp_error($attach_id)) {
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                            wp_update_attachment_metadata($attach_id, $attach_data);
                            $product->set_image_id($attach_id); // Set as featured image
                            break; // Stop after successfully setting one image
                        } else {
                            $errors[] = [
                                'itemNumber' => $sku,
                                'error' => "Failed to create attachment for image $image_url: " . $attach_id->get_error_message()
                            ];
                        }
                    } else {
                        $errors[] = [
                            'itemNumber' => $sku,
                            'error' => "Failed to download image from $image_url"
                        ];
                    }
                }

                // Save the product metadata
                $save_result = $product->save();
                if (!$save_result) {
                    throw new Exception('Failed to save product metadata (Data: ' . json_encode($product_data) . ')');
                }

                $imported[] = [
                    'id' => $product_id,
                    'title' => $product->get_name(),
                    'sku' => $sku
                ];
            } catch (Exception $e) {
                $errors[] = [
                    'itemNumber' => $sku,
                    'error' => $e->getMessage()
                ];
                // Continue with the next product even if an error occurs
                continue;
            }
        }

        // Clear any buffered output and send JSON response
        ob_clean();
        wp_send_json([
            "status" => 200,
            "imported" => $imported,
            "errors" => $errors,
            "message" => count($imported) . " products imported successfully, " . count($errors) . " errors encountered"
        ]);
    }
}

// Initialize Plugin
new ScanSourceSearchAPIPlugin();
<?php
/*
 * GetStock.net API Example
 * by Tony Nguyễn
 * Telegram: @nguyenduytan
 *
 * Installation Instructions:
 * 1. Install Composer if not already installed: https://getcomposer.org/
 * 2. Run `composer require guzzlehttp/guzzle` in your project directory to install Guzzle HTTP Client.
 * 3. Replace 'YOUR_TOKEN_HERE' with your actual token received via email from Getstocks.net API.
 * 4. Upload this file to your web server and access it via a browser.
 * 5. Ensure PHP has write permissions for the 'uploads', 'orders', and 'hooked' directories if used elsewhere.
 */

require_once 'vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class GetStocksApiExample {
    private $client;
    private $token = ''; // Replace with your actual token

    public function __construct() {
        $this->client = new Client([
            'base_uri' => 'https://getstocks.net/',
            'timeout'  => 30,
        ]);
    }

    public function getInfo($link, $ispre = 1) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/getinfo', [
                'query' => ['token' => $this->token],
                'form_params' => ['link' => $link, 'ispre' => $ispre]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['status'] === 200 ? $data : ['status' => 'error', 'message' => $data['message']];
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getLink($link, $ispre, $type) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/getlink', [
                'query' => ['token' => $this->token],
                'form_params' => ['link' => $link, 'ispre' => $ispre, 'type' => $type]
            ]);
            $data = json_decode($response->getBody(), true);
            return $data['status'] === 200 ? $data : ['status' => 'error', 'message' => $data['message']];
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function checkDownloadStatus($slug, $id, $ispre, $type) {
        if (empty($this->token)) return ['status' => 'error', 'message' => 'No token available'];
        try {
            $response = $this->client->post('/api/v1/download-status', [
                'query' => ['token' => $this->token],
                'form_params' => ['slug' => $slug, 'id' => $id, 'ispre' => $ispre, 'type' => $type]
            ]);
            $data = json_decode($response->getBody(), true);
            if ($data['status'] === 200 && $data['result']['status'] === 1) {
                $data['result']['downloadLink'] = "https://getstocks.net/api/v1/download/{$data['result']['itemDCode']}?token={$this->token}";
            }
            return $data['status'] === 200 ? $data : ['status' => 'error', 'message' => $data['message']];
        } catch (RequestException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

$api = new GetStocksApiExample();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    switch ($_POST['action']) {
        case 'getInfo':
            echo json_encode($api->getInfo($_POST['link']));
            break;
        case 'getLink':
            echo json_encode($api->getLink($_POST['link'], $_POST['ispre'], $_POST['type']));
            break;
        case 'checkDownloadStatus':
            echo json_encode($api->checkDownloadStatus($_POST['slug'], $_POST['id'], $_POST['ispre'], $_POST['type']));
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Getstocks.net API Example</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: Arial, sans-serif; }
        #downloadInfo { 
            display: none; 
            margin-top: 30px; 
            margin-bottom: 30px; 
            padding: 15px; 
            background: #f8f9fa; 
            border: 1px solid #dee2e6; 
            border-radius: 4px; 
        }
        #downloadInfo img { max-width: 100px; height: auto; }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="container my-5">
        <h1 class="text-center mb-2">Gettocks API</h1>
        <p class="text-center text-muted mb-4">with love by Tony Nguyễn</p>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="linkInput" placeholder="Enter link (e.g., https://www.freepik.com/...)">
                    <select class="form-select" id="typeSelect" style="display: none;"></select>
                    <button class="btn btn-primary" id="downloadBtn" disabled><i class="bi bi-download"></i></button>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div id="downloadInfo" class="row align-items-center">
                    <div class="col-md-2"><img id="itemThumb" src="" alt="Thumbnail"></div>
                    <div class="col-md-10 d-flex align-items-center">
                        <div>
                            <p id="providerText" class="mb-1"></p>
                            <p id="idText" class="mb-1"></p>
                        </div>
                        <button class="btn btn-secondary ms-3" id="processBtn">Processing...</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            let timeoutId;

            function resetForm() {
                $('#typeSelect').hide().empty();
                $('#downloadBtn').prop('disabled', true).html('<i class="bi bi-download"></i>');
                $('#downloadInfo').hide();
            }

            $('#linkInput').on('input', function() {
                const link = $(this).val().trim();
                if (!link) {
                    resetForm();
                    return;
                }

                $('#downloadBtn').html('<i class="bi bi-arrow-repeat spin"></i>'); // Icon processing khi nhập link
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { action: 'getInfo', link: link },
                    success: function(data) {
                        if (data.status === 200 && data.result.support) {
                            const support = data.result.support;
                            $('#typeSelect').empty().show();
                            $.each(support.type, function(key, value) {
                                $('#typeSelect').append(`<option value="${key}">${value}</option>`);
                            });
                            $('#downloadBtn').prop('disabled', false).html('<i class="bi bi-download"></i>');
                            $('#downloadBtn').data('support', support);
                        } else {
                            Swal.fire('Error', data.message || 'Không get được info của link', 'error');
                            resetForm();
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Failed to fetch info: ' + error, 'error');
                        resetForm();
                    }
                });
            });

            $('#downloadBtn').on('click', function() {
                const support = $(this).data('support');
                const type = $('#typeSelect').val();
                if (!support || !type) return;

                $('#downloadInfo').show();
                $('#itemThumb').attr('src', support.itemthumb);
                $('#providerText').text('Provider: ' + support.slug);
                $('#idText').text('ID: ' + support.id);
                $('#processBtn').text('Processing...').prop('disabled', true);

                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { 
                        action: 'getLink', 
                        link: support.id2, 
                        ispre: support.ispre, 
                        type: type 
                    },
                    success: function(data) {
                        if (data.status === 200 && data.result) {
                            checkStatus(data.result.provSlug, data.result.itemID, data.result.isPremium, data.result.itemType);
                        } else {
                            Swal.fire('Error', data.message || 'Không process được', 'error');
                            $('#downloadInfo').hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'Failed to get link: ' + error, 'error');
                        $('#downloadInfo').hide();
                    }
                });
            });

            function checkStatus(slug, id, ispre, type) {
                let startTime = Date.now();
                const checkInterval = setInterval(function() {
                    if (Date.now() - startTime > 60000) {
                        clearInterval(checkInterval);
                        Swal.fire('Error', 'Download timed out after 60 seconds', 'error');
                        $('#downloadInfo').hide();
                        return;
                    }

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { action: 'checkDownloadStatus', slug: slug, id: id, ispre: ispre, type: type },
                        success: function(data) {
                            if (data.status === 200 && data.result.status === 1) {
                                clearInterval(checkInterval);
                                $('#processBtn').text('Processed').prop('disabled', false)
                                    .off('click').on('click', function() {
                                        window.location.href = data.result.downloadLink;
                                    });
                            }
                        },
                        error: function(xhr, status, error) {
                            clearInterval(checkInterval);
                            Swal.fire('Error', 'Failed to check status: ' + error, 'error');
                            $('#downloadInfo').hide();
                        }
                    });
                }, 5000);
            }
        });
    </script>
</body>
</html>
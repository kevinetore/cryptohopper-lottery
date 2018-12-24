<?php
  session_start();
  // App Credentials
  define('CLIENT_ID', $_ENV["CRYPTOHOPPER_CLIENT_ID"]);
  define('CLIENT_SECRET', $_ENV["CRYPTOHOPPER_CLIENT_SECRET"]);

  # Base Url
  $cryptohopper_url = 'https://www.cryptohopper.com';
  $base_api_url = 'https://api.cryptohopper.com';
  $redirect_uri = 'http://localhost:8888/callback';

  # Generate state
  $_SESSION['state'] = substr(md5(rand()), 0, 7);

  # Base Authorization url
  $path = '/oauth/access_tokens'
    .'?app_key='
    .CLIENT_ID
    .'&state='
    .urlencode($_SESSION['state'])
    .'&redirect_uri='
    .urlencode($redirect_uri);

  # Create Signature
  $signature = hash_hmac('sha512', $path, CLIENT_SECRET);
  $authorizationUrl = $cryptohopper_url.$path.'&signature='.$signature;

  $data = false;
  if(is_array($_GET) && !empty($_GET)){
    $data = true;
    if (isset($_GET['access_key'])) {
      $_SESSION['access_key'] = $_GET['access_key'];
    }

    if (isset($_GET['access_secret'])) {
      $_SESSION['access_secret'] = $_GET['access_secret'];
    }

    $hopper_endpoint = '/v1/hopper';

    $signature_hopper = hash_hmac('sha512', $hopper_endpoint, $_SESSION['access_secret']);
    $headers = array(
      'x-api-access-key: ' . $_SESSION['access_key'],
      'x-api-signature: ' . $signature_hopper
    );

    // Create Curl handler
    $ch = curl_init($base_api_url.$hopper_endpoint);
    $value = ApiGetRequest($ch, $headers);
    $hoppers = $value['data']['hoppers'];
  }

  # User wants to place a random bet
  if (isset($_GET['getHopperConfig']))
  { 
    GetHopperConfig($_GET['getHopperConfig']);
  }

  # Fetch Hopper with allowed coins
  function GetHopperConfig($hopper_id)
  {
    $hopper_config_endpoint = "/v1/hopper/" .$hopper_id.  "/config";
    $signature = hash_hmac('sha512', $hopper_config_endpoint, $_SESSION['access_secret']);
    $headers = array(
      'x-api-access-key: ' . $_SESSION['access_key'],
      'x-api-signature: ' . $signature
    );

    $ch = curl_init('https://api.cryptohopper.com' . $hopper_config_endpoint);
    $request_coins = ApiGetRequest($ch, $headers);
    $available_coins = $request_coins['data']['allowed_coins'];

    # Get a random coin from allowed coins
    $coin_index = array_rand($available_coins, 1);
    $selected_coin = $available_coins[$coin_index];
    echo "<h3 style='color:white;'>Coin selected: " . $selected_coin . "</h3>";
    PlaceRandomBet($coin, $hopper_id);
  }

  # Send a POST to place the bet
  function PlaceRandomBet($coin, $hopper_id) {
    $order_endpoint = "/hopper/" .$hopper_id. "/order";
    $signature = hash_hmac('sha512', $order_endpoint, $_SESSION['access_secret']);
    $headers = array(
      'x-api-access-key: ' . $_SESSION['access_key'],
      'x-api-signature: ' . $signature
    );

    # Set the body value
    $order_body = array(
      "order_type" => "buy",
      "coin" => $coin,
      "amount" => 0,
      "price" => 0,
      "trailing_buy" => 0,
      "trailing_buy_pct" => 0,
      "pct_profit" => 0
    );
    
    $order_string = json_encode($order_body);

    $ch = curl_init('https://api.cryptohopper.com' . $order_endpoint);
    $place_bet = ApiPostRequest($ch, $headers, $order_string);
    
    echo "<h3 style='color:white;'>Placed Bet: </h3>";
    echo "<p style='color: white;'>"; print_r($place_bet); echo "</p>";
  }

  function ApiGetRequest($handler, $api_headers) {
    curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handler, CURLOPT_HTTPHEADER, $api_headers);
    $result = curl_exec($handler);
    return json_decode($result, true);
  }

  function ApiPostRequest($handler, $api_headers, $order_body) {
    curl_setopt($handler, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($handler, CURLOPT_POSTFIELDS, $order_body);
    curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handler, CURLOPT_HTTPHEADER, $api_headers);
    $result = curl_exec($handler);
    return json_decode($result, true);
  }

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Cryptohopper Lottery</title>
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</head>

<body>
  <section class="container">
    <div class="container--bg"></div>
    <div class="container--body">
      <h1 class="container--body__text">Cryptohopper Lottery</h2>
        <div class="container--body__links">
          <? if (!$data): ?>
            <a class="btn btn-primary active" role="button" aria-pressed="true" href="<?= $authorizationUrl; ?>">Login with Cryptohopper</a>
            <span class="or">or</span>
            <a href="https://www.cryptohopper.com/create-account" class="active" role="button" aria-pressed="true">Create account</a>
          <? else: ?>
            <?php foreach($hoppers as $hopper): ?>
              <div class="card">
                <img class="card-img-top" src="https://www.newsbtc.com/wp-content/uploads/2018/10/fomo2-flat-700x400.jpg" alt="Card image cap">
                <div class="card-body">
                  <h5 class="card-title"><?= $hopper['name'] ?></h5>
                  <p class="card-text">Hopper ID: <?= $hopper['id'] ?></p>
                  <a href="?getHopperConfig=<?= $hopper['id'] ?>" class="btn btn-primary">Place Bet</a>
                </div>  
              </div>
           <?php endforeach; ?>
          <? endif; ?>
        </div>

        <? if ($data): ?>  
          <a href="/" class="btn btn-dark">Home</a>
        <? endif; ?>
    </div>
  </section>  


  </section>

  <footer class="footer">
    <p>Photo by <strong>Eberhard Grossgasteiger</strong> on <a class="footer--link" href="https://unsplash.com" target="_blank">Unsplash</a></p>
  </footer>
</body>

</html>
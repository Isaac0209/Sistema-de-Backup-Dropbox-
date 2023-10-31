<?php 
  $clientID = 'y3dfc1gnaym1iiz';
  $clientSecret = 'y1gtu4o303n8s7e';
  $accessCode = 'HmGDmNFhu-4AAAAAAAAAHBVf8BKTpngnKCfV4vD-LHw';

    $data = array(
    'code' => $accessCode,
    'grant_type' => 'authorization_code'
    );

    $authorizationHeader = 'Basic ' . base64_encode($clientID . ':' . $clientSecret);

    $options = array(
    'http' => array(
    'header' => "Authorization: $authorizationHeader\r\n" .
                "Content-Type: application/x-www-form-urlencoded\r\n",
    'method' => 'POST',
    'content' => http_build_query($data),
    )
    );

    $context = stream_context_create($options);
    $response = file_get_contents('https://api.dropboxapi.com/oauth2/token', false, $context);

    if ($response !== false) {
    $responseData = json_decode($response, true);
    print_r($responseData['refresh_token']); 

    } else {
    echo 'Erro na solicitação de token.';
    }

?>
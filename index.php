<?php
date_default_timezone_set('America/Sao_Paulo'); 
setlocale(LC_TIME, 'pt_BR.utf8');

$horaEspecifica = '0:00:00';
$horaEspecificaHoje = strtotime(date('Y-m-d') . ' ' . $horaEspecifica);
$diferencaSegundos = time() - $horaEspecificaHoje;
$clientID = 'y3dfc1gnaym1iiz';
$clientSecret = 'y1gtu4o303n8s7e';
$refreshToken = 'QLFdSU04nEgAAAAAAAAAAbcYyMSDr1ijG6wzPLjirP26ItQGfRKupqgrvU-PQAxb';
$horas = floor($diferencaSegundos / 3600);
if($horas <= 1){
$banco = json_decode(file_get_contents('clientes.json'));
foreach($banco as $itens){
    
    $servername = $itens->servername;
    $username = $itens->username;
    $password = $itens->password;
    $dbname = $itens->dbname;

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }

    $sql = "SHOW TABLES";
    $result = $conn->query($sql);

    $sqlString = "-- Consulta SQL gerada em " . date('Y-m-d H:i:s') . "\n\n";

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_row()) {
            $nomeTabela = $row[0];
            $sqlDefinicao = "SHOW CREATE TABLE `$nomeTabela`"; 
            $resultDefinicao = $conn->query($sqlDefinicao);
    
            if ($resultDefinicao->num_rows > 0) {
                $rowDefinicao = $resultDefinicao->fetch_row();
                $sqlCriacaoTabela = $rowDefinicao[1] . ";\n\n";
                $sqlString .= $sqlCriacaoTabela;
    
                $sqlSelect = "SELECT * FROM `$nomeTabela`"; 
                $resultSelect = $conn->query($sqlSelect);
    
                if ($resultSelect->num_rows > 0) {
                    while ($rowSelect = $resultSelect->fetch_assoc()) {
                        $sqlInsert = "INSERT INTO `$nomeTabela` ("; 
                        $values = "VALUES (";
    
                        foreach ($rowSelect as $coluna => $valor) {
                            $sqlInsert .= "`$coluna`, "; 
                            $valor = str_replace("'", "''", $valor); 
                            $values .= "'$valor', ";
                        }
    
                        $sqlInsert = rtrim($sqlInsert, ', ') . ") ";
                        $values = rtrim($values, ', ') . ");\n";
    
                        $sqlString .= $sqlInsert . $values;
                    }
                }
            }
        }
    }
    $caminhoArquivo = 'banco.sql';
    $teste = "";
    file_put_contents($caminhoArquivo, $sqlString);
    $zipFile = $itens->nomePasta . '.zip';

    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        foreach ($itens->Pastas as $item) {
            $pastaNf = $item->caminho;
            $pastaNf = str_replace('\\', '/', realpath($pastaNf));

            if (is_dir($pastaNf)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pastaNf));
                
                foreach ($iterator as $arquivo) {
                    
                    $caminho = str_replace('\\', '/', $arquivo->getPathname());

                    $arquivoLocal = substr($caminho, strlen($pastaNf) + 1);
                    $nome = $item->nome;
                    if ($arquivo->isDir()) {
                        $zip->addEmptyDir($nome .'/' . $arquivoLocal);
                    } else {
                        $zip->addFile($caminho, $nome .'/' . $arquivoLocal);
                    }
                }
            }
           
        }
        $zip->addFile($caminhoArquivo, 'banco.sql');
        $zip->close();
      
      
      
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientID,
            'client_secret' => $clientSecret
        );
        
        $options = array(
            'http' => array(
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            )
        );
        
        $context = stream_context_create($options);
        $response = file_get_contents('https://api.dropboxapi.com/oauth2/token', false, $context);
        
        if ($response !== false) {
            $responseData = json_decode($response, true);
            $accessToken = $responseData['access_token'];
            $localFilePath = $zipFile; 
            $remoteFolderPath = "/".$itens->nomePasta; 
            $fileName =  $itens->nomePasta ."_" . date('Y-m-d H:i:s'). ".zip"; 
            if(!verificarPasta($accessToken, $remoteFolderPath)){
                criarPasta($accessToken,$remoteFolderPath);
                upload($accessToken, $remoteFolderPath, $localFilePath, $fileName);

            }else{
                upload($accessToken, $remoteFolderPath, $localFilePath, $fileName);
            }
            $conn->close();
            unlink($caminhoArquivo);
            unlink($zipFile);
            echo "Backup Feito!";
        } else {
            echo 'Erro na solicitação de novo token de acesso.';
        }
 
      
    }
}
function upload($api, $nomePasta, $localFilePath, $nome){
    try{
    $accessToken = $api;
    $chunkSize = 100 * 1024 * 1024; // 100 MB
    $fileSize = filesize($localFilePath);
    $handle = fopen($localFilePath, 'rb');
    
    $sessionId = '';
    $offset = 0;
    
    // Iniciar a sessão de upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://content.dropboxapi.com/2/files/upload_session/start');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/octet-stream',
        'Dropbox-API-Arg: ' . json_encode(array('close' => false))
    ));
    $response = curl_exec($ch);
    $responseData = json_decode($response, true);
    curl_close($ch);
    print_r($response);
    
    $sessionId = $responseData['session_id'];
    
    while (!feof($handle)) {
        $content = fread($handle, $chunkSize);
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://content.dropboxapi.com/2/files/upload_session/append_v2');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: ' . json_encode(array('cursor' => array('session_id' => $sessionId, 'offset' => $offset), 'close' => false))
        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_exec($ch);
        curl_close($ch);
    
        $offset += $chunkSize;
    }
    
    fclose($handle);
    
    // Finalizar a sessão de upload
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://content.dropboxapi.com/2/files/upload_session/finish');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/octet-stream',
        'Dropbox-API-Arg: ' . json_encode(array('cursor' => array('session_id' => $sessionId, 'offset' => $fileSize), 'commit' => array('path' => $nomePasta . "/" . $nome, 'mode' => 'add', 'autorename' => true, 'mute' => false)))
    ));
    $response = curl_exec($ch);
    curl_close($ch);
    
    print_r($response);
}catch(Exception $error){
    echo $error;
}
}
}else{
    echo "Não está na hora do backup!";
}
function criarPasta($api, $nomePasta) {
  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/files/create_folder_v2');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $api,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('path' => $nomePasta)));

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function verificarPasta($api, $nomePasta) {
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.dropboxapi.com/2/files/get_metadata');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $api,
        'Content-Type: application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('path' => $nomePasta)));

    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if (isset($responseData['error'])) {
       return false;
    } else {
       return true;
    }
}


?>
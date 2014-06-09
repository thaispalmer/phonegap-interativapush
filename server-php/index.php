<?php

require_once('config.php');

//
function envioPushGCM($apiKey,$registrationIds,$messageData) {
	$headers = array("Content-Type:" . "application/json", "Authorization:" . "key=" . $apiKey);
	$data = array(
		'data' => $messageData,
		'registration_ids' => $registrationIds
	);

	$conn = curl_init();
	curl_setopt($conn, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($conn, CURLOPT_URL, "https://android.googleapis.com/gcm/send");
	curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($conn, CURLOPT_POSTFIELDS, json_encode($data));

	$result = curl_exec($conn);
	curl_close($conn);

	return $result;
}

function envioPushAPNS($certificado,$senha,$deviceToken,$messageData,$sandbox = false) {
	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', $certificado);
	stream_context_set_option($ctx, 'ssl', 'passphrase', $senha);
	$sock = stream_socket_client($sandbox == true ? 'ssl://gateway.sandbox.push.apple.com:2195' : 'ssl://gateway.push.apple.com:2195' , $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

	$data = json_encode(array('aps' => $messageData));
	$pre = chr(0) . pack('n',32);
	$pos = pack('n', strlen($data)) . $data;
	$status = array();
	foreach ($deviceToken as $token) {
		$msg = $pre . pack('H*',$token) . $pos;
		$result = fwrite($sock, $msg, strlen($msg));
		if ($result) $status['succ'][] = $token;
		else $status['fail'][] = $token;
	}
	fclose($sock);
	return $status;
}

function montaMensagem($tipo,$mensagem,$titulo,$contagem,$audio) {
	$payload = array();
	if ($tipo == 'gcm') {
		$payload['message'] = $mensagem;
		$payload['title'] = $titulo;
		if ($contagem) $payload['msgcnt'] = $contagem;
		if ($audio) $payload['soundname'] = $audio;
	}
	elseif ($tipo == 'apns') {
		$payload['alert'] = $titulo . "\n" . $mensagem;
		if ($contagem) $payload['badge'] = $contagem;
		if ($audio) $payload['sound'] = $audio;
	}
	return $payload;
}


//
$db = mysqli_connect($_config['mysql']['host'],$_config['mysql']['user'],$_config['mysql']['pass'],$_config['mysql']['database'],$_config['mysql']['port']) or die("Erro: " . mysqli_error($db));

if ($_GET) {
	if ($_GET['action'] == 'assign') {
		if ($_GET['nome'] && $_GET['registro'] && $_GET['plataforma']) {
			$query = "SELECT nome,id_registro FROM push_registros WHERE nome='" . $_GET['nome'] . "'";
			$result = $db->query($query);
			$row = mysqli_fetch_array($result);
			if ($row['nome'] == $_GET['nome']) {
				$query = "UPDATE push_registros SET plataforma='" . $_GET['plataforma'] . "', registro='" . $_GET['registro'] . "' WHERE id_registro=" . $row['id_registro']; 
				$result = $db->query($query);
				echo '{ "success" : 2 }'; // registro atualizado
			}
			else {
				$query = "INSERT INTO push_registros (nome, registro, plataforma) VALUES ('" . $_GET['nome'] . "','" . $_GET['registro'] . "','" . $_GET['plataforma'] . "')";
				$result = $db->query($query);
				echo '{ "success" : 1 }'; // registro adicionado
			}
		}
		else {
			echo '{ "error" : 1 }'; // assign incompleto
		}
	}
	if (($_GET['action'] == 'push') && ($_POST)) {
		$android = array();
		$ios = array();
		if (isset($_POST['ids']) && ($_POST['mensagem']) && ($_POST['titulo'])) {
			$ids = '';
			foreach ($_POST['ids'] as $id) $ids .= ','.$id;
			echo 'Lista de IDs: ' . substr($ids,1) . '<hr/>';

			//Android
			$query = "SELECT registro FROM push_registros WHERE (plataforma = 'android' OR plataforma = 'Android') AND id_registro IN (" . substr($ids, 1) . ")";
			echo 'Query android: ' . $query . '<br/>';
			$result = $db->query($query);
			while ($row = mysqli_fetch_array($result)) $android[] = $row['registro'];

			//iOS - uso != android pq não sei qual o nome da plataforma que vai ser registrada
			$query = "SELECT registro FROM push_registros WHERE (plataforma != 'android' AND plataforma != 'Android') AND id_registro IN (" . substr($ids, 1) . ")";
			echo 'Query iOS: ' . $query . '<hr/>';
			$result = $db->query($query);
			while ($row = mysqli_fetch_array($result)) $ios[] = $row['registro'];

			//
			echo '<b>Android</b><br/><pre>' . print_r($android,true) . '</pre><br/>';
			echo '<b>iOS</b><br/><pre>' . print_r($ios,true) . '</pre><br/><hr/>';

			//Envio de mensagens
			if (count($android) > 0) {
				echo 'Enviando para Android...<br/>';
				$msgandroid = montaMensagem('gcm',$_POST['mensagem'],$_POST['titulo'],$_POST['contagem'],$_POST['audio']);
				$resposta = envioPushGCM($_config['gcm']['apikey'],$android,$msgandroid);
				echo $resposta . '<br/><br/>';
			}

			if (count($ios) > 0) {
				echo 'Enviando para iOS...<br/>';
				$msgios = montaMensagem('apns',$_POST['mensagem'],$_POST['titulo'],$_POST['contagem'],$_POST['audio']);
				$resposta = envioPushAPNS($_config['apns']['certificado'],$_config['apns']['senha'],$ios,$msgios,true);
				echo 'Envios bem sucedidos: ' . count($resposta['succ']) . '<br/>';
				foreach($resposta['succ'] as $token) echo '[' . $token . '] ';
				echo '<br/>Envios com falha: ' . count($resposta['fail']) . '<br/>';
			}
		}
		else { echo 'Selecione algum dispositivo, preencha todos os campos obrigatórios e tente novamente.'; }
	}
}
else {
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Interativa Push</title>
</head>
<body>
	<h1>Interativa Push</h1>
	<hr/>
	<h3>Registros</h3>
	<form action="index.php?action=push" method="POST">
	<table>
		<tr>
			<td></td>
			<td><b>Nome</b></td>
			<td><b>Plataforma</b></td>
		</tr>
<?php
	// listar assigns
	$query = "SELECT * FROM push_registros ORDER BY id_registro ASC";
	$result = $db->query($query);
	while ($row = mysqli_fetch_array($result)) {
		echo '<tr><td><input type="checkbox" value="' . $row['id_registro'] . '" name="ids[]" type="checkbox"/></td><td>' . $row['nome'] . '</td><td><span title="' . $row['registro'] . '">' . $row['plataforma'] . '</span></td></tr>';
	}
?>
	</table>
	<hr/>
	Mensagem: <input type="text" name="mensagem"/><br/>
	Titulo: <input type="text" name="titulo"/><br/>
	Contagem: <input type="text" name="contagem"/> <small>(opcional)</small><br/>
	Som de notificação personalizado:
	<select name="audio">
		<option value="">Padrão do dispositivo</option>
		<option value="plastik.wav">Plastik</option>
		<option value="capisci.wav">Capisci</option>
		<option value="scifiish.wav">Sci-fi-ish</option>
	</select> <small>(opcional)</small><br/>
	<input type="submit" value="Enviar Mensagem"/>
	</form>
</body>
</html>

<?php } ?>

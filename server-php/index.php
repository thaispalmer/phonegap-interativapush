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

function montaMensagem($tipo,$mensagem,$titulo,$contagem,$audio) {
	if ($tipo == 'gcm') {
		return array(
			'message' => $mensagem,
			'title' => $titulo,
			'msgcnt' => $contagem,
			'soundname' => $audio
		);
	}
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
				echo 'Envio para android em desenvolvimento.';
			}
		}
	}
}
else {
?>

<html>
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
	Contagem: <input type="text" name="contagem"/><br/>
	Arquivo de audio: <input type="text" name="audio"/><br/>
	<input type="submit" value="Enviar Mensagem"/>
	</form>
</body>
</html>

<?php } ?>

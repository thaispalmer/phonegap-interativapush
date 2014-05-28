<?php

require_once('config.php');
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
        if ($_GET['action'] == 'push') {
                foreach($id as $registro) {
                        $query = "SELECT registro,plataforma FROM push_registros WHERE id_registro=" . $registro;
                        $result = $db->query($query);
                        $row = mysqli_fetch_array($result);
                        if ($row['plataforma'] == 'android') {
                                // codigo do push gcm
                        }
                        else {
                                // codigo do push apns
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
        <table>
                <tr>
                        <td><b>Nome</b></td>
                        <td><b>Plataforma</b></td>
                        <td></td>
                </tr>

<?php
        // listar assigns
        $query = "SELECT * FROM push_registros ORDER BY id_registro ASC";
        $result = $db->query($query);
        while ($row = mysqli_fetch_array($result)) {
                echo '<tr><td>' . $row['nome'] . '</td><td><span title="' . $row['registro'] . '">' . $row['plataforma'] . '</span></td><td>' . $row['id_registro'] . '</td></tr>';
        }
?>

        </table>
</body>
</html>

<?php } ?>

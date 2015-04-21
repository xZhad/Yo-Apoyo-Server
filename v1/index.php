<?php

require_once '../include/DbHandler.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();


function verifyRequiredParams($required_fields) {
	$error = false;
	$error_fields = "";
	$request_params = array();
	$request_params = $_REQUEST;

	if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
		$app = \Slim\Slim::getInstance();
		parse_str($app->request()->getBody(), $request_params);
	}

	foreach ($required_fields as $field) {
		if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
			$error = true;
			$error_fields .= $field . ', ';
		}
	}

	if ($error) {
		$response = array();
		$app = \Slim\Slim::getInstance();
		$response["error"] = true;
		$response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
		echoRespnse(400, $response);
		$app->stop();
	}
}

/**
* Echoing json response to client
* @param String $status_code Http response code
* @param Int $response Json response
*/
function echoRespnse($status_code, $response) {
	$app = \Slim\Slim::getInstance();

	$app->status($status_code);

	$app->contentType('application/json');

	echo json_encode($response);
}

/**
* Crea un nuevo incidente en la base de datos
* method POST
* params - id_dispositivo, categoria, imagen, descripcion, geopos
* url - /incidente
*/
$app->post('/incidente', function() use ($app) {
	verifyRequiredParams(array('id_dispositivo', 'categoria', 'descripcion', 'geopos'));

	$id_dispositivo = $app->request()->post('id_dispositivo');
	$categoria = $app->request()->post('categoria');
	$descripcion = $app->request()->post('descripcion');
	$geopos = $app->request()->post('geopos');

	$response = array();
	$db = new DbHandler();

	if (isset($_FILES['imagen'])) {
		$imagen = $_FILES['imagen'];
		$now = new DateTime();
		$datetime = $now->format('Y-m-d_H-i-s');
		$name = uniqid('img_'.$datetime.'_');
		$ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);

		if (move_uploaded_file($imagen['tmp_name'], 'images/' . $name . '.' . $ext) === true) {
			$imagen_url = "api/v1/images/" . $name . '.' . $ext;
			$result = $db->createIncidente($id_dispositivo, $categoria, $imagen_url, $descripcion, $geopos);
			if ($result != NULL) {
				$response["error"] = false;
				$response["data"] = $result;
			} else {
				$response["error"] = true;
				$response["data"] = "Failed to create 'incidente'. Please try again";
			}
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to upload image. Please try again";
		}

	} else {
		$imagen_url = "";
		$result = $db->createIncidente($id_dispositivo, $categoria, $imagen_url, $descripcion, $geopos);
		if ($result != NULL) {
			$response["error"] = false;
			$response["data"] = $result;
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to create 'incidente'. Please try again";
		}
	}

	echoRespnse(201, $response);
});

/**
* Listar todos los incidentes con el estado definido
* method GET
* url /incidentes/estado
*/
$app->get('/incidentes/:estado', function($estado) {
	$response = array();
	$db = new DbHandler();

	$result = $db->getAllIncidentes($estado);

	$response["error"] = false;
	$response["data"] = array();

	foreach ($result as $key => $val) {
		array_push($response["data"], $val);
	}

	echoRespnse(200, $response);
});

/**
* Listar solo el incidente con el id correspondiente y sus interacciones
* method GET
* url /incidente/id
*/
$app->get('/incidente/:id', function($id) {
	$response = array();
	$db = new DbHandler();

	$result = $db->getIncidente($id);
	$result2 = $db->getInteracciones($id);

	$response["error"] = false;
	$response["data"]["incidente"] = array();
	$response["data"]["interacciones"] = array();

	foreach ($result as $key => $val) {
		array_push($response["data"]["incidente"], $val);
	}

	foreach ($result2 as $key => $val) {
		array_push($response["data"]["interacciones"], $val);
	}

	echoRespnse(200, $response);
});

/**
* Apoyar un incidente
* method POST
* params - tipo, id_dispositivo, id_incidente, opt:id_interaccion
* url /apoyar
*/
$app->post('/apoyar', function() use ($app) {
	verifyRequiredParams(array('id_dispositivo', 'id_incidente', 'tipo'));

	$id_dispositivo = $app->request()->post('id_dispositivo');
	$id_incidente = $app->request()->post('id_incidente');
	$tipo = $app->request()->post('tipo');

	$response = array();
	$db = new DbHandler();

	if (isset($_POST['id_interaccion'])) {
		$id_interaccion = $app->request()->post('id_interaccion');

		$result = $db->apoyaInteraccion($id_dispositivo, $id_incidente, $id_interaccion, $tipo);

		if ($result != NULL) {
			$response["error"] = false;
			$response["data"] = $result;
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to create 'apoyo'. Please try again";
		}
	} else {
		$result = $db->apoyaIncidente($id_dispositivo, $id_incidente, $tipo);

		if ($result != NULL) {
			$response["error"] = false;
			$response["data"] = $result;
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to create 'apoyo'. Please try again";
		}
	}

	echoRespnse(201, $response);
});

/**
* Crea una nueva interaccion en la base de datos
* method POST
* params - id_dispositivo, id_incidente, imagen, descripcion, solucionado
* url - /interaccion
*/
$app->post('/interaccion', function() use ($app) {
	verifyRequiredParams(array('id_dispositivo', 'id_incidente', 'descripcion', 'solucionado'));

	$id_dispositivo = $app->request()->post('id_dispositivo');
	$id_incidente = $app->request()->post('id_incidente');
	$descripcion = $app->request()->post('descripcion');
	$solucionado = $app->request()->post('solucionado');

	$response = array();
	$db = new DbHandler();

	if (isset($_FILES['imagen'])) {
		$imagen = $_FILES['imagen'];
		$now = new DateTime();
		$datetime = $now->format('Y-m-d_H-i-s');
		$name = uniqid('img_'.$datetime.'_');
		$ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);

		if (move_uploaded_file($imagen['tmp_name'], '/Users/Zhad/Sites/api/v1/images/' . $name . '.' . $ext) === true) {
			$imagen_url = "api/v1/images/" . $name . '.' . $ext;
			$result = $db->createInteraccion($id_dispositivo, $id_incidente, $imagen_url, $descripcion, $solucionado);
			if ($result != NULL) {
				$response["error"] = false;
				$response["data"] = $result;
			} else {
				$response["error"] = true;
				$response["data"] = "Failed to create 'interaccion'. Please try again";
			}
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to upload image. Please try again";
		}

	} else {
		$imagen_url = "";
		$result = $db->createInteraccion($id_dispositivo, $id_incidente, $imagen_url, $descripcion, $solucionado);
		if ($result != NULL) {
			$response["error"] = false;
			$response["data"] = $result;
		} else {
			$response["error"] = true;
			$response["data"] = "Failed to create 'interaccion'. Please try again";
		}
	}

	echoRespnse(201, $response);
});

$app->run();
?>
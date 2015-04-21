<?php

/**
* Class to handle all db operations
* This class will have CRUD methods for database tables
*
* @author Ravi Tamada
*/
class DbHandler {

	private $conn;

	function __construct() {
		require_once dirname(__FILE__) . '/DbConnect.php';
		// opening db connection
		$db = new DbConnect();
		$this->conn = $db->connect();
	}

	public function createIncidente($id_dispositivo, $categoria, $imagen_url, $descripcion, $geopos) {
		$now = new DateTime();
		$datetime = $now->format('Y-m-d H:i:s');
		if(empty($imagen_url)) {
			$stmt = $this->conn->prepare("INSERT INTO incidente (id_dispositivo, categoria, descripcion, estado, geopos, fecha_publicacion)
											VALUES(?, ?, ?, 1, ?, ?)");
			$stmt->bind_param("iisss", $id_dispositivo, $categoria, $descripcion, $geopos, $datetime);
		} else {
			$stmt = $this->conn->prepare("INSERT INTO incidente (id_dispositivo, categoria, url_imagen, descripcion, estado, geopos, fecha_publicacion)
											VALUES(?, ?, ?, ?, 1, ?, ?)");
			$stmt->bind_param("iissss", $id_dispositivo, $categoria, $imagen_url, $descripcion, $geopos, $datetime);
		}
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			$new_incidente_id = $this->conn->insert_id;
			$stmt = $this->conn->prepare("INSERT INTO puntaje (id_dispositivo, id_incidente, tipo, fecha)
											VALUES(?, ?, 1, ?)");
			$stmt->bind_param("iis", $id_dispositivo, $new_incidente_id, $datetime);
			$result2 = $stmt->execute();
			$stmt->close();

			if ($result2) {
				return $new_incidente_id;
			} else {
				return NULL;
			}
		} else {
			return NULL;
		}
	}

	public function getAllIncidentes($estado) {
		$stmt = $this->conn->prepare("SELECT incidente.id_incidente, incidente.categoria, incidente.descripcion, count(puntaje.tipo) AS apoyo FROM incidente
										LEFT JOIN puntaje ON incidente.id_incidente = puntaje.id_incidente
										WHERE puntaje.id_interaccion is NULL
										AND puntaje.tipo = 1
										AND incidente.estado = ?
										GROUP BY incidente.id_incidente
										ORDER BY incidente.fecha_publicacion DESC");
		$stmt->bind_param("i", $estado);
		if ($stmt->execute()) {
			$incidente = array();
			$incidentes = array();
			$stmt->bind_result(
				$incidente['id_incidente'],
				$incidente['categoria'],
				$incidente['descripcion'],
				$incidente['apoyo']
			);
			while ($stmt->fetch()) {
				$incidentes[] = array(
					'id_incidente'	=> $incidente['id_incidente'],
					'categoria'		=> $incidente['categoria'],
					'descripcion'	=> $incidente['descripcion'],
					'apoyo'			=> $incidente['apoyo']
				);
			}
			$stmt->close();
			return $incidentes;
		} else {
			return NULL;
		}
	}

	public function getIncidente($id_incidente) {
		$stmt = $this->conn->prepare("SELECT incidente.id_incidente, incidente.categoria, incidente.url_imagen, incidente.descripcion, incidente.estado, incidente.geopos, incidente.fecha_publicacion, incidente.fecha_resolucion, count(puntaje.tipo) AS apoyo FROM incidente
										LEFT JOIN puntaje ON incidente.id_incidente = puntaje.id_incidente
										WHERE incidente.id_incidente = ?
										GROUP BY incidente.id_incidente
										ORDER BY apoyo DESC");
		$stmt->bind_param("i", $id_incidente);
		if ($stmt->execute()) {
			$tmp = array();
			$stmt->bind_result(
				$tmp['id_incidente'],
				$tmp['categoria'],
				$tmp['url_imagen'],
				$tmp['descripcion'],
				$tmp['estado'],
				$tmp['geopos'],
				$tmp['fecha_publicacion'],
				$tmp['fecha_resolucion'],
				$tmp['apoyo']
			);
			$stmt->fetch();
			$incidente[] = array(
				'id_incidente'		=> $tmp['id_incidente'],
				'categoria'			=> $tmp['categoria'],
				'url_imagen'		=> $tmp['url_imagen'],
				'descripcion'		=> $tmp['descripcion'],
				'estado'			=> $tmp['estado'],
				'geopos'			=> $tmp['geopos'],
				'fecha_publicacion'	=> $tmp['fecha_publicacion'],
				'fecha_resolucion'	=> $tmp['fecha_resolucion'],
				'apoyo'				=> $tmp['apoyo']
			);
			$stmt->close();
			return $incidente;
		} else {
			return NULL;
		}
	}

	public function getInteracciones($id_incidente) {
		$stmt = $this->conn->prepare("SELECT interaccion.id_interaccion, interaccion.url_imagen, interaccion.descripcion, interaccion.solucionado, interaccion.fecha FROM interaccion
										WHERE interaccion.id_incidente = ?
										ORDER BY interaccion.fecha ASC");

		$stmt->bind_param("i", $id_incidente);
		if ($stmt->execute()) {
			$interaccion = array();
			$interacciones = array();
			$stmt->bind_result(
				$interaccion['id_interaccion'],
				$interaccion['url_imagen'],
				$interaccion['descripcion'],
				$interaccion['solucionado'],
				$interaccion['fecha']
			);
			while ($stmt->fetch()) {
				$interacciones[] = array(
					'id_interaccion'	=> $interaccion['id_interaccion'],
					'url_imagen'		=> $interaccion['url_imagen'],
					'descripcion'		=> $interaccion['descripcion'],
					'solucionado'		=> $interaccion['solucionado'],
					'fecha'				=> $interaccion['fecha']
				);
			}
			$stmt->close();
			return $interacciones;
		} else {
			return NULL;
		}
	}

	public function apoyaIncidente($id_dispositivo, $id_incidente, $tipo) {
		$now = new DateTime();
		$datetime = $now->format('Y-m-d H:i:s');
		$stmt = $this->conn->prepare("INSERT INTO puntaje (id_dispositivo, id_incidente, tipo, fecha)
										VALUES(?, ?, ?, ?)");
		$stmt->bind_param("iiis", $id_dispositivo, $id_incidente, $tipo, $datetime);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			$new_apoyo_id = $this->conn->insert_id;
			return $new_apoyo_id;
		} else {
			return NULL;
		}
	}

	public function apoyaInteraccion($id_dispositivo, $id_incidente, $id_interaccion, $tipo) {
		$now = new DateTime();
		$datetime = $now->format('Y-m-d H:i:s');
		$stmt = $this->conn->prepare("INSERT INTO puntaje (id_dispositivo, id_incidente, id_interaccion, tipo, fecha)
										VALUES(?, ?, ?, ?, ?)");
		$stmt->bind_param("iiiis", $id_dispositivo, $id_incidente, $id_interaccion, $tipo, $datetime);
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			$new_apoyo_id = $this->conn->insert_id;
			return $new_apoyo_id;
		} else {
			return NULL;
		}
	}

	public function createInteraccion($id_dispositivo, $id_incidente, $imagen_url, $descripcion, $solucionado) {
		$now = new DateTime();
		$datetime = $now->format('Y-m-d H:i:s');
		if(empty($imagen_url)) {
			$stmt = $this->conn->prepare("INSERT INTO interaccion (id_dispositivo, id_incidente, descripcion, solucionado, fecha)
											VALUES(?, ?, ?, ?, ?)");
			$stmt->bind_param("iisis", $id_dispositivo, $id_incidente, $descripcion, $solucionado, $datetime);
		} else {
			$stmt = $this->conn->prepare("INSERT INTO interaccion (id_dispositivo, id_incidente, url_imagen, descripcion, solucionado, fecha)
											VALUES(?, ?, ?, ?, ?, ?)");
			$stmt->bind_param("iissis", $id_dispositivo, $id_incidente, $imagen_url, $descripcion, $solucionado, $datetime);
		}
		$result = $stmt->execute();
		$stmt->close();

		if ($result) {
			$new_interaccion_id = $this->conn->insert_id;
			return $new_interaccion_id;
		} else {
			return NULL;
		}
	}

}

?>
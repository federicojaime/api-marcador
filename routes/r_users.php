<?php

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Firebase\JWT\JWT;
use Slim\Factory\AppFactory;
use \Firebase\JWT\Key;
use \PHPMailer\PHPMailer\PHPMailer;
use \PHPMailer\PHPMailer\SMTP;
use \PHPMailer\PHPMailer\Exception;
use \objects\Users;
use \utils\Validate;
use \utils\Prepare;


function sendTokenRegister($email, $nombre, $token)
{
	$mail = new PHPMailer(true); //true = enable exceptions
	$mail->CharSet = "UTF-8";
	try {
		//Server settings
		//$mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
		$mail->SMTPDebug = 0;                      					//Enable verbose debug output
		$mail->isSMTP();                                            //Send using SMTP
		$mail->Host       = $_ENV["SMTP_HOST"];                     //Set the SMTP server to send through
		$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
		$mail->Username   = $_ENV["SMTP_USERNAME"];                 //SMTP username
		$mail->Password   = $_ENV["SMTP_PASSWORD"];                 //SMTP password
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
		$mail->Port       = $_ENV["SMTP_PORT"];                     //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

		//Recipients
		$mail->setFrom($_ENV["SMTP_USERNAME"], $_ENV["SMTP_SENDER_NAME"]);
		$mail->addAddress($email, $nombre);     //Add a recipient
		/*
			$mail->addAddress("ellen@example.com");               //Name is optional
			$mail->addReplyTo("info@example.com", "Information");
			$mail->addCC("cc@example.com");
			$mail->addBCC("bcc@example.com");
			*/

		//Attachments
		/*
			$mail->addAttachment("/var/tmp/file.tar.gz");         //Add attachments
			$mail->addAttachment("/tmp/image.jpg", "new.jpg");    //Optional name
			*/

		//Content
		$urlLink = $_ENV["APP_URL"] . "register/token/" . $token;

		$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = "Sheep Shit - Registro";
		$body = <<<EOD
				<h3>Hola <strong>{$nombre}</strong>.</h3>
				<br/>
				<p>Bienvenido/a a <strong>Sheep Shit</strong>.</p>
				<p>Para completar el registro por favor visita este <a href='{$urlLink}' target='_blank' rel='nofollow noopener'>enlace</a>.</p>
			EOD;
		$mail->Body    = $body;
		$mail->AltBody = "Hola " . $nombre . ", bienvenido/a a Sheep Shit, para completar el registro por favor visita este enlace: " . $urlLink;
		$mail->send();
		//echo "Message has been sent";
		return true;
	} catch (Exception $e) {
		//echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		return false;
	}
};

function sendTokenRecover($email, $token)
{
	$mail = new PHPMailer(true);
	$mail->CharSet = "UTF-8";
	try {
		$mail->SMTPDebug = 0;
		$mail->isSMTP();
		$mail->Host       = $_ENV["SMTP_HOST"];
		$mail->SMTPAuth   = true;
		$mail->Username   = $_ENV["SMTP_USERNAME"];
		$mail->Password   = $_ENV["SMTP_PASSWORD"];
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
		$mail->Port       = $_ENV["SMTP_PORT"];

		$mail->setFrom($_ENV["SMTP_USERNAME"], $_ENV["SMTP_SENDER_NAME"]);
		$mail->addAddress($email);

		$urlLink = $_ENV["APP_URL"] . "recover/token/" . $token;

		$mail->isHTML(true);
		$mail->Subject = "Sheep Shit - Recuperación de contraseña";
		$body = <<<EOD
				<h3>Sheep Shit</h3>
				<br/>
				<p>Ups.. al perecer olvidaste tu contraseña, para poder generar una nueva por favor visita este <a href='{$urlLink}' target='_blank' rel='nofollow noopener'>enlace</a>.</p>
			EOD;
		$mail->Body    = $body;
		$mail->AltBody = "Sheep Shit - Ups.. al perecer olvidaste tu contraseña, para poder generar una nueva por favor visita este enlace: " . $urlLink;
		$mail->send();
		//echo "Message has been sent";
		return true;
	} catch (Exception $e) {
		//echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		return false;
	}
};

//[GET]

$app->get("/users", function (Request $request, Response $response, array $args) {
	$users = new Users($this->get("db"));
	$resp = $users->getUsers()->getResult();
	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->get("/user/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
	$users = new Users($this->get("db"));
	$resp = $users->getUser($args["id"])->getResult();
	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->get("/user/register/temp/{token}", function (Request $request, Response $response, array $args) {
	$resp = new \stdClass();
	$users = new Users($this->get("db"));
	$resp = $users->getUserTemp($args["token"])->getResult();
	if (isset($resp->data->id)) {
		$id = $resp->data->id;
		$resp = $users->moveTempUser($id)->getResult();
	} else {
		$resp->ok = false;
		$resp->msg = "El token [" . $args["token"] . "] no es válido.";
		$resp->data = false;
	}
	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->get("/user/password/temp/{token}", function (Request $request, Response $response, array $args) {
	$resp = new \stdClass();
	$users = new Users($this->get("db"));
	$respT = clone ($users->getUserPasswordTemp($args["token"])->getResult());
	if (isset($respT->data->id)) {
		$id = $respT->data->id;
		$resp = $users->moveTempUser($id)->getResult();
		$resp->data = $respT->data;
	} else {
		$resp->ok = false;
		$resp->msg = "El token [" . $args["token"] . "] no es válido.";
		$resp->data = false;
	}
	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->get("/user/token/validate/{token}", function (Request $request, Response $response, array $args) {
	$resp = new \stdClass();
	$resp->ok = false;
	$resp->msg = "El token [" . $args["token"] . "] no es válido.";

	//$jwt = JWT::encode($payload, $_SERVER["JWT_SECRET_KEY"], $_SERVER["JWT_ALGORITHM"]);
	$token = str_replace("Bearer ", "", $args["token"]);
	try {
		$decoded = JWT::decode($token, new Key($_SERVER["JWT_SECRET_KEY"], $_SERVER["JWT_ALGORITHM"]));
		$decoded->data->jwt = $args["token"];
		$resp->ok = true;
		$resp->msg = "";
		$resp->data = $decoded->data;
	} catch (\Throwable $th) {
		$resp->data = false;
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

//[POST]

$app->post("/user", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"email" => [
			"type" => "string",
			"isValidMail" => true,
			"unique" => "users"
		],
		"firstname" => [
			"type" => "string",
			"min" => 3,
			"max" => 50
		],
		"lastname" => [
			"type" => "string",
			"min" => 3,
			"max" => 50
		],
		"password" => [
			"type" => "string",
			"min" => 3,
			"max" => 20
		]
	];

	$validacion = new Validate($this->get("db"));
	$validacion->validar($fields, $verificar);

	$resp = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$users = new Users($this->get("db"));
		$resp = $users->setUser($fields)->getResult();
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->post("/user/login", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"email" => [
			"type" => "string",
			"isValidMail" => true
		],
		"password" => [
			"type" => "string",
			"min" => 3,
			"max" => 20
		]
	];

	$validacion = new Validate();
	$validacion->validar($fields, $verificar);

	$resp = new \stdClass();
	$resp->ok = false;
	$resp->msg = "Nombre de usuario o contraseña incorrecto.";
	$resp->data = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$users = new Users($this->get("db"));
		$existe = $users->userExist($fields["email"]);
		if ($existe && password_verify($fields["password"], $existe->password)) {
			$iss = "http://hansjal.com";
			$aud = "http://hansjal.com";
			$iat = time();
			$exp = $iat + (3600 * 2); // Expire (2Hs)
			$nbf = $iat;
			$token = array(
				"iss" => $iss,
				"aud" => $aud,
				"iat" => $iat,
				"exp" => $exp,
				"nbf" => $nbf,
				"data" => array(
					"id" => $existe->id,
					"firstname" => $existe->firstname,
					"lastname" => $existe->lastname,
					"email" => $existe->email
				)
			);
			unset($existe->password);
			$jwt = JWT::encode($token, $_ENV["JWT_SECRET_KEY"], $_ENV["JWT_ALGORITHM"]);
			$existe->jwt = "Bearer " . $jwt;
			$resp->ok = true;
			$resp->msg = "Usuario autorizado.";
			$resp->data = $existe;
		}
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 401);
});

$app->post("/user/register", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"email" => [
			"type" => "string",
			"isValidMail" => true,
			"unique" => "users"
		],
		"firstname" => [
			"type" => "string",
			"min" => 3,
			"max" => 50
		],
		"lastname" => [
			"type" => "string",
			"min" => 3,
			"max" => 50
		],
		"password" => [
			"type" => "string",
			"min" => 3,
			"max" => 20
		]
	];

	$validacion = new Validate($this->get("db"));
	$validacion->validar($fields, $verificar);

	$resp = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$fields["token"] = Prepare::randomString();
		$users = new Users($this->get("db"));
		$resp = $users->setRegister($fields)->getResult();
		//Envío de mail con el token!!!
		$nombre = Prepare::UCfirst($fields["lastname"]) . ", " . Prepare::UCfirst($fields["firstname"]);
		$resp->mailSend = sendTokenRegister($fields["email"], $nombre, $fields["token"]);
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->post("/user/password/recover", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"email" => [
			"type" => "string",
			"isValidMail" => true
		]
	];

	$validacion = new Validate($this->get("db"));
	$validacion->validar($fields, $verificar);

	$resp = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$fields["token"] = Prepare::randomString();
		$users = new Users($this->get("db"));
		$resp = $users->setTempRecovery($fields); //->getResult();
		//Envío de mail con el token!!!
		if ($resp->ok) {
			$resp->mailSend = sendTokenRecover($fields["email"], $fields["token"]);
		}
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

//[PATCH]

$app->patch("/user/password", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"id" => [
			"type" => "number",
			"min" => 1
		],
		"password" => [
			"type" => "string",
			"min" => 3,
			"max" => 20
		]
	];

	$validacion = new Validate($this->get("db"));
	$validacion->validar($fields, $verificar);

	$resp = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$users = new Users($this->get("db"));
		$resp = $users->setNewPassword($fields["id"], $fields["password"])->getResult();
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});

$app->patch("/user/password/temp/update", function (Request $request, Response $response, array $args) {
	$fields = $request->getParsedBody();

	$verificar = [
		"id" => [
			"type" => "number",
			"min" => 1
		],
		"iduser" => [
			"type" => "number",
			"min" => 1
		],
		"password" => [
			"type" => "string",
			"min" => 3,
			"max" => 20
		],
		"token" => [
			"type" =>  "string",
			"min" => 10,
			"max" => 10,
			"exist" => "passrecovery"
		]
	];

	$validacion = new Validate($this->get("db"));
	$validacion->validar($fields, $verificar);

	$resp = null;

	if ($validacion->hasErrors()) {
		$resp = $validacion->getErrors();
	} else {
		$users = new Users($this->get("db"));
		$resp = $users->setNewPassword($fields["iduser"], $fields["password"])->getResult();
		if ($resp->ok) {
			$users->deleteTempPassword($fields["id"]);
		}
	}

	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});


//[DELETE]

$app->delete("/user/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
	$users = new Users($this->get("db"));
	$resp = $users->deleteUser($args["id"])->getResult();
	$response->getBody()->write(json_encode($resp));
	return $response
		->withHeader("Content-Type", "application/json")
		->withStatus($resp->ok ? 200 : 409);
});


$app->post('/registro-entradas', function ($request, $response, $args) {
	$data = $request->getParsedBody();
	$geolocalizacion = json_encode($data['geolocalizacion']);
	$timestamp = $data['timestamp'];

	// Obtener la conexión a la base de datos desde el contenedor
	$db = $this->get('db');

	// Preparar e insertar en la base de datos
	$sql = "INSERT INTO horarios (geolocalizacion, tipo, timestamp) VALUES (:geolocalizacion, 'entrada', :timestamp)";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(':geolocalizacion', $geolocalizacion);
	$stmt->bindParam(':timestamp', $timestamp);
	$stmt->execute();

	// Crear una respuesta JSON
	$response->getBody()->write(json_encode(['success' => true]));

	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->post('/registro-salidas', function ($request, $response, $args) {
	$data = $request->getParsedBody();
	$geolocalizacion = json_encode($data['geolocalizacion']);
	$timestamp = $data['timestamp'];

	// Obtener la conexión a la base de datos desde el contenedor
	$db = $this->get('db');

	// Preparar e insertar en la base de datos
	$sql = "INSERT INTO horarios (geolocalizacion, tipo, timestamp) VALUES (:geolocalizacion, 'salida', :timestamp)";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(':geolocalizacion', $geolocalizacion);
	$stmt->bindParam(':timestamp', $timestamp);
	$stmt->execute();

	// Crear una respuesta JSON
	$response->getBody()->write(json_encode(['success' => true]));

	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

$app->post('/registro-almuerzs', function ($request, $response, $args) {
	$data = $request->getParsedBody();
	$geolocalizacion = json_encode($data['geolocalizacion']);
	$timestamp = $data['timestamp'];

	// Obtener la conexión a la base de datos desde el contenedor
	$db = $this->get('db');

	// Preparar e insertar en la base de datos
	$sql = "INSERT INTO horarios (geolocalizacion, tipo, timestamp) VALUES (:geolocalizacion, 'almuerzo', :timestamp)";
	$stmt = $db->prepare($sql);
	$stmt->bindParam(':geolocalizacion', $geolocalizacion);
	$stmt->bindParam(':timestamp', $timestamp);
	$stmt->execute();

	// Crear una respuesta JSON
	$response->getBody()->write(json_encode(['success' => true]));

	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});
// Ruta para registrar un empleado con su descriptor facial
$app->post('/registera', function (Request $request, Response $response) {
	$data = $request->getParsedBody();
	$nombre = $data['nombre'];
	$email = $data['email'];
	$cargo = $data['cargo'];
	$descriptor = json_encode($data['descriptor']);

	$sql = "INSERT INTO empleados (nombre, email, cargo, descriptor) VALUES (:nombre, :email, :cargo, :descriptor)";
	try {
		// Obtener la conexión a la base de datos desde el contenedor
		$db = $this->get('db');
		$stmt = $db->prepare($sql);
		$stmt->bindParam(':nombre', $nombre);
		$stmt->bindParam(':email', $email);
		$stmt->bindParam(':cargo', $cargo);
		$stmt->bindParam(':descriptor', $descriptor);
		$stmt->execute();

		$response->getBody()->write(json_encode(['message' => 'Empleado registrado exitosamente']));
		return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
	} catch (PDOException $e) {
		$response->getBody()->write(json_encode(['error' => $e->getMessage()]));
		return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
	}
});



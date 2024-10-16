<?php

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Illuminate\Database\Capsule\Manager as Capsule;

require(__DIR__ . "/vendor/autoload.php");

$container = new Container();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'codeo.site',
    'database'  => 'u565673608_marcador',
    'username'  => 'u565673608_marcador',
    'password'  => 'Qwerty2024@',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

$container->set('db', function () use ($capsule) {
    return $capsule;
});

AppFactory::setContainer($container);

$app = AppFactory::create();

$app->setBasePath(preg_replace("/(.*)\/.*/", "$1", $_SERVER["SCRIPT_NAME"]));

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new \Tuupola\Middleware\JwtAuthentication([
    "ignore" => [
        $app->getBasePath() . "/api/register",
        $app->getBasePath() . "/api/checkin",
        $app->getBasePath() . "/api/checkout",
        $app->getBasePath() . "/api/lunch_start",
        $app->getBasePath() . "/api/lunch_end",
        $app->getBasePath() . "/api/branches",
        $app->getBasePath() . "/api/employees",
    ],
    "secret" => $_ENV["JWT_SECRET_KEY"],
    "algorithm" => $_ENV["JWT_ALGORITHM"], 
    "attribute" => "jwt",
    "error" => function ($response, $arguments) {
        $data["ok"] = false;
        $data["msg"] = $arguments["message"];
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );
        return $response->withHeader("Content-Type", "application/json");
    }
]));

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->options("/{routes:.+}", function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader("Access-Control-Allow-Origin", "*")
        ->withHeader("Access-Control-Allow-Headers", "X-Requested-With, Content-Type, Accept, Origin, Authorization")
        ->withHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE, PATCH, OPTIONS");
});

function calculateEuclideanDistance($descriptor1, $descriptor2)
{
    $distance = 0.0;
    for ($i = 0; $i < count($descriptor1); $i++) {
        $distance += ($descriptor1[$i] - $descriptor2[$i]) ** 2;
    }
    return sqrt($distance);
}

$app->post('/api/register', function (Request $request, Response $response, $args) {
    $data = $request->getParsedBody();

    $nombre = $data['nombre'] ?? null;
    $cedula = $data['cedula'] ?? null;
    $sucursal_id = $data['sucursal_id'] ?? null;
    $pin = $data['pin'] ?? null;
    $descriptors = $data['descriptors'] ?? null;
    $fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
    $estado = $data['estado'] ?? 'activo';
    $fecha_ingreso = date('Y-m-d');

    if ($nombre && $cedula && $sucursal_id && $pin && $descriptors && $fecha_nacimiento) {
        // Verificar si ya existe un empleado con la misma cédula
        $existingEmployee = Capsule::table('employees')
            ->where('cedula', $cedula)
            ->first();

        if ($existingEmployee) {
            $response->getBody()->write(json_encode([
                'message' => 'El empleado ya está registrado',
                'nombre' => $existingEmployee->nombre
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $hashedPin = password_hash($pin, PASSWORD_DEFAULT);

        $employeeId = Capsule::table('employees')->insertGetId([
            'nombre' => $nombre,
            'cedula' => $cedula,
            'sucursal_id' => $sucursal_id,
            'fecha_ingreso' => $fecha_ingreso,
            'estado' => $estado,
            'fecha_nacimiento' => $fecha_nacimiento,
            'pin' => $hashedPin,
            'descriptors' => json_encode($descriptors),
        ]);

        $response->getBody()->write(json_encode(['message' => 'Empleado registrado correctamente', 'employee_id' => $employeeId]));
        return $response->withHeader('Content-Type', 'application/json');
    } else {
        $response->getBody()->write(json_encode(['message' => 'Datos incompletos']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
});


$app->get('/api/employees', function (Request $request, Response $response, $args) {
    $employees = Capsule::table('employees')->select('id', 'nombre', 'descriptors')->get();

    $employeesData = $employees->map(function ($employee) {
        return [
            'id' => $employee->id,
            'nombre' => $employee->nombre,
            'descriptors' => json_decode($employee->descriptors),
        ];
    });

    $response->getBody()->write(json_encode($employeesData));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/branches', function (Request $request, Response $response, $args) {
    $branches = Capsule::table('branches')->get();
    $response->getBody()->write(json_encode($branches));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/api/checkin', function (Request $request, Response $response, $args) {
    return handleCheckInOut($request, $response, 'check_in');
});

$app->post('/api/checkout', function (Request $request, Response $response, $args) {
    return handleCheckInOut($request, $response, 'check_out');
});

$app->post('/api/lunch_start', function (Request $request, Response $response, $args) {
    return handleCheckInOut($request, $response, 'lunch_start');
});

$app->post('/api/lunch_end', function (Request $request, Response $response, $args) {
    return handleCheckInOut($request, $response, 'lunch_end');
});

function handleCheckInOut($request, $response, $type)
{
    $data = $request->getParsedBody();

    $descriptor = $data['descriptor'] ?? null;
    $pin = $data['pin'] ?? null;

    if ($descriptor && $pin) {
        $employees = Capsule::table('employees')->get();

        $queryDescriptor = $descriptor;
        $threshold = 0.5;

        foreach ($employees as $employee) {
            $storedDescriptors = json_decode($employee->descriptors);

            foreach ($storedDescriptors as $storedDescriptor) {
                $distance = calculateEuclideanDistance($queryDescriptor, $storedDescriptor);

                if ($distance < $threshold) {
                    if (password_verify($pin, $employee->pin)) {
                        $timestamp = date('Y-m-d H:i:s');
                        $today = date('Y-m-d');

                        // Verificar si ya existe un registro para hoy
                        $existingRecord = Capsule::table('attendance')
                            ->where('employee_id', $employee->id)
                            ->whereDate('check_in', $today)
                            ->first();

                        switch ($type) {
                            case 'check_in':
                                if ($existingRecord) {
                                    return respondWithError($response, 'Ya has fichado entrada hoy');
                                }
                                Capsule::table('attendance')->insert([
                                    'employee_id' => $employee->id,
                                    'check_in' => $timestamp,
                                ]);
                                $message = 'Entrada fichada correctamente';
                                break;

                            case 'check_out':
                                if (!$existingRecord || $existingRecord->check_out) {
                                    return respondWithError($response, 'No puedes fichar salida sin haber fichado entrada primero');
                                }
                                Capsule::table('attendance')
                                    ->where('id', $existingRecord->id)
                                    ->update(['check_out' => $timestamp]);
                                $message = 'Salida fichada correctamente';
                                break;

                            case 'lunch_start':
                                if (!$existingRecord || $existingRecord->lunch_start) {
                                    return respondWithError($response, 'No puedes iniciar el almuerzo sin haber fichado entrada o ya has iniciado el almuerzo');
                                }
                                Capsule::table('attendance')
                                    ->where('id', $existingRecord->id)
                                    ->update(['lunch_start' => $timestamp]);
                                $message = 'Inicio de almuerzo registrado correctamente';
                                break;

                            case 'lunch_end':
                                if (!$existingRecord || !$existingRecord->lunch_start || $existingRecord->lunch_end) {
                                    return respondWithError($response, 'No puedes finalizar el almuerzo sin haberlo iniciado primero');
                                }
                                Capsule::table('attendance')
                                    ->where('id', $existingRecord->id)
                                    ->update(['lunch_end' => $timestamp]);
                                $message = 'Fin de almuerzo registrado correctamente';
                                break;

                            default:
                                return respondWithError($response, 'Acción no válida');
                        }

                        $response->getBody()->write(json_encode(['message' => $message]));
                        return $response->withHeader('Content-Type', 'application/json');
                    } else {
                        return respondWithError($response, 'PIN incorrecto', 401);
                    }
                }
            }
        }

        return respondWithError($response, 'Empleado no reconocido', 401);
    } else {
        return respondWithError($response, 'Datos incompletos', 400);
    }
}

function respondWithError($response, $message, $status = 400)
{
    $response->getBody()->write(json_encode(['message' => $message]));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

// Manejar rutas no encontradas
$app->map(["GET", "POST", "PUT", "DELETE", "PATCH"], "/{routes:.+}", function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();

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

// Configurar la zona horaria predeterminada para PHP
date_default_timezone_set('America/Costa_Rica');

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

// Configurar la zona horaria para MySQL (se ajustará por empleado más adelante)
$capsule->getConnection()->statement("SET time_zone = '-06:00';"); // Costa Rica por defecto

$container->set('db', function () use ($capsule) {
    return $capsule;
});

AppFactory::setContainer($container);

$app = AppFactory::create();

$app->setBasePath(preg_replace("/(.*)\/.*$/", "$1", $_SERVER["SCRIPT_NAME"]));

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->add(new \Tuupola\Middleware\JwtAuthentication([
    "ignore" => [
        $app->getBasePath() . "/api/register",
        $app->getBasePath() . "/api/check_in",
        $app->getBasePath() . "/api/check_out",
        $app->getBasePath() . "/api/lunch_start",
        $app->getBasePath() . "/api/lunch_end",
        $app->getBasePath() . "/api/bonus_check_in",
        $app->getBasePath() . "/api/start_101",
        $app->getBasePath() . "/api/end_101",
        $app->getBasePath() . "/api/overtime_start",
        $app->getBasePath() . "/api/overtime_end",
        $app->getBasePath() . "/api/employees",
        $app->getBasePath() . "/api/employee-status",
        $app->getBasePath() . "/api/employee-branch",
        $app->getBasePath() . "/api/branches",
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

function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371; // en kilómetros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;
    return $distance;
}

function respondWithError($response, $message, $status = 400)
{
    $response->getBody()->write(json_encode(['message' => $message]));
    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

function setTimezoneForBranch($branchId)
{
    $timeZone = $branchId == 20 ? 'America/Argentina/Buenos_Aires' : 'America/Costa_Rica';
    date_default_timezone_set($timeZone);
    $offset = $timeZone == 'America/Argentina/Buenos_Aires' ? '-03:00' : '-06:00';
    Capsule::connection()->statement("SET time_zone = '{$offset}';");
}

function handleCheckInOut($request, $response, $type)
{
    $data = $request->getParsedBody();

    $descriptor = $data['descriptor'] ?? null;
    $pin = $data['pin'] ?? null;
    $userLatitude = $data['latitude'] ?? null;
    $userLongitude = $data['longitude'] ?? null;
    $employeeId = $data['employee_id'] ?? null;

    if ($descriptor && $pin && $employeeId) {
        $employee = Capsule::table('employees')
            ->join('branches', 'employees.sucursal_id', '=', 'branches.id')
            ->select('employees.*', 'branches.latitud as branch_latitude', 'branches.longitud as branch_longitude', 'branches.id as branch_id')
            ->where('employees.id', $employeeId)
            ->first();

        if (!$employee) {
            return respondWithError($response, 'Empleado no encontrado', 404);
        }

        setTimezoneForBranch($employee->branch_id);

        $storedDescriptors = json_decode($employee->descriptors);
        $threshold = 0.5;
        $matched = false;

        foreach ($storedDescriptors as $storedDescriptor) {
            $distance = calculateEuclideanDistance($descriptor, $storedDescriptor);
            if ($distance < $threshold) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            return respondWithError($response, 'Descriptor facial no coincide', 401);
        }

        if (password_verify($pin, $employee->pin)) {
            if ($employee->branch_latitude !== null && $employee->branch_longitude !== null && $userLatitude !== null && $userLongitude !== null) {
                $distanceToWork = calculateDistance($userLatitude, $userLongitude, $employee->branch_latitude, $employee->branch_longitude);
                if ($distanceToWork > 1) {
                    return respondWithError($response, 'Estás demasiado lejos de tu lugar de trabajo para fichar', 403);
                }
            }

            $today = date('Y-m-d');

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
                        'check_in' => Capsule::raw('NOW()'),
                    ]);
                    $message = 'Entrada fichada correctamente';
                    break;

                case 'check_out':
                    if (!$existingRecord || $existingRecord->check_out) {
                        return respondWithError($response, 'No puedes fichar salida sin haber fichado entrada primero o ya has fichado salida');
                    }
                    Capsule::table('attendance')
                        ->where('id', $existingRecord->id)
                        ->update(['check_out' => Capsule::raw('NOW()')]);
                    $message = 'Salida fichada correctamente';
                    break;

                case 'lunch_start':
                    if (!$existingRecord || $existingRecord->lunch_start) {
                        return respondWithError($response, 'No puedes iniciar el almuerzo sin haber fichado entrada o ya has iniciado el almuerzo');
                    }
                    Capsule::table('attendance')
                        ->where('id', $existingRecord->id)
                        ->update(['lunch_start' => Capsule::raw('NOW()')]);
                    $message = 'Inicio de almuerzo registrado correctamente';
                    break;

                case 'lunch_end':
                    if (!$existingRecord || !$existingRecord->lunch_start || $existingRecord->lunch_end) {
                        return respondWithError($response, 'No puedes finalizar el almuerzo sin haberlo iniciado primero');
                    }
                    Capsule::table('attendance')
                        ->where('id', $existingRecord->id)
                        ->update(['lunch_end' => Capsule::raw('NOW()')]);
                    $message = 'Fin de almuerzo registrado correctamente';
                    break;

                case 'bonus_check_in':
                    if ($existingRecord && $existingRecord->bonus_check_in) {
                        return respondWithError($response, 'Ya has fichado el bono hoy');
                    }
                    if (!$existingRecord) {
                        Capsule::table('attendance')->insert([
                            'employee_id' => $employee->id,
                            'check_in' => Capsule::raw('NOW()'),
                            'bonus_check_in' => Capsule::raw('NOW()'),
                        ]);
                    } else {
                        Capsule::table('attendance')
                            ->where('id', $existingRecord->id)
                            ->update(['bonus_check_in' => Capsule::raw('NOW()')]);
                    }
                    $message = 'Bono fichado correctamente';
                    break;

                case 'start_101':
                    if ($existingRecord && $existingRecord->start_101 && $existingRecord->end_101 && $existingRecord->start_101_2) {
                        return respondWithError($response, 'Ya has iniciado los dos 101 disponibles hoy');
                    }
                    if (!$existingRecord || !$existingRecord->start_101) {
                        Capsule::table('attendance')
                            ->where('id', $existingRecord->id)
                            ->update(['start_101' => Capsule::raw('NOW()')]);
                        $message = 'Inicio del primer 101 registrado correctamente';
                    } elseif ($existingRecord->end_101 && !$existingRecord->start_101_2) {
                        Capsule::table('attendance')
                            ->where('id', $existingRecord->id)
                            ->update(['start_101_2' => Capsule::raw('NOW()')]);
                        $message = 'Inicio del segundo 101 registrado correctamente';
                    } else {
                        return respondWithError($response, 'No puedes iniciar otro 101 en este momento');
                    }
                    break;

                case 'end_101':
                    if (!$existingRecord || (!$existingRecord->start_101 && !$existingRecord->start_101_2)) {
                        return respondWithError($response, 'No puedes finalizar 101 sin haberlo iniciado primero');
                    }
                    if ($existingRecord->start_101 && !$existingRecord->end_101) {
                        Capsule::table('attendance')
                            ->where('id', $existingRecord->id)
                            ->update(['end_101' => Capsule::raw('NOW()')]);
                        $message = 'Fin del primer 101 registrado correctamente';
                    } elseif ($existingRecord->start_101_2 && !$existingRecord->end_101_2) {
                        Capsule::table('attendance')
                            ->where('id', $existingRecord->id)
                            ->update(['end_101_2' => Capsule::raw('NOW()')]);
                        $message = 'Fin del segundo 101 registrado correctamente';
                    } else {
                        return respondWithError($response, 'No hay 101 activo para finalizar');
                    }
                    break;

                case 'overtime_start':
                    if ($existingRecord && $existingRecord->overtime_start) {
                        return respondWithError($response, 'Ya has iniciado hora extra hoy');
                    }
                    Capsule::table('attendance')
                        ->where('id', $existingRecord->id)
                        ->update(['overtime_start' => Capsule::raw('NOW()')]);
                    $message = 'Inicio de hora extra registrado correctamente';
                    break;

                case 'overtime_end':
                    if (!$existingRecord || !$existingRecord->overtime_start || $existingRecord->overtime_end) {
                        return respondWithError($response, 'No puedes finalizar hora extra sin haberla iniciado primero');
                    }
                    Capsule::table('attendance')
                        ->where('id', $existingRecord->id)
                        ->update(['overtime_end' => Capsule::raw('NOW()')]);
                    $message = 'Fin de hora extra registrado correctamente';
                    break;

                default:
                    return respondWithError($response, 'Acción no válida');
            }

            $response->getBody()->write(json_encode(['message' => $message]));
            return $response->withHeader('Content-Type', 'application/json');
        } else {
            return respondWithError($response, 'PIN incorrecto', 401);
        }
    } else {
        return respondWithError($response, 'Datos incompletos', 400);
    }
}

$app->post('/api/check_in', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'check_in');
});

$app->post('/api/check_out', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'check_out');
});

$app->post('/api/lunch_start', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'lunch_start');
});

$app->post('/api/lunch_end', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'lunch_end');
});

$app->post('/api/bonus_check_in', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'bonus_check_in');
});

$app->post('/api/start_101', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'start_101');
});

$app->post('/api/end_101', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'end_101');
});

$app->post('/api/overtime_start', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'overtime_start');
});

$app->post('/api/overtime_end', function (Request $request, Response $response) {
    return handleCheckInOut($request, $response, 'overtime_end');
});

$app->get('/api/employee-status/{id}', function (Request $request, Response $response, $args) {
    $employeeId = $args['id'];

    $employee = Capsule::table('employees')
        ->join('branches', 'employees.sucursal_id', '=', 'branches.id')
        ->select('employees.*', 'branches.latitud as branch_latitude', 'branches.longitud as branch_longitude', 'branches.id as branch_id')
        ->where('employees.id', $employeeId)
        ->first();

    if (!$employee) {
        return $response->withStatus(404)->withJson(['error' => 'Empleado no encontrado']);
    }

    setTimezoneForBranch($employee->branch_id);

    $today = date('Y-m-d');

    $attendance = Capsule::table('attendance')
        ->where('employee_id', $employeeId)
        ->whereDate('check_in', $today)
        ->first();

    $status = [
        'checkedIn' => false,
        'checkedOut' => false,
        'bonusCheckedIn' => false,
        'lunchStarted' => false,
        'lunchEnded' => false,
        '_101Started' => false,
        '_101Ended' => false,
        '_101_2Started' => false,
        '_101_2Ended' => false,
        'overtimeStarted' => false,
        'overtimeEnded' => false,
        'available101Count' => 2,
        'branchLatitude' => $employee->branch_latitude,
        'branchLongitude' => $employee->branch_longitude,
    ];

    if ($attendance) {
        $status['checkedIn'] = true;
        $status['checkedOut'] = $attendance->check_out !== null;
        $status['bonusCheckedIn'] = $attendance->bonus_check_in !== null;
        $status['lunchStarted'] = $attendance->lunch_start !== null;
        $status['lunchEnded'] = $attendance->lunch_end !== null;
        $status['_101Started'] = $attendance->start_101 !== null;
        $status['_101Ended'] = $attendance->end_101 !== null;
        $status['_101_2Started'] = $attendance->start_101_2 !== null;
        $status['_101_2Ended'] = $attendance->end_101_2 !== null;
        $status['overtimeStarted'] = $attendance->overtime_start !== null;
        $status['overtimeEnded'] = $attendance->overtime_end !== null;

        if ($status['_101Started'] && $status['_101Ended']) {
            $status['available101Count']--;
        }
        if ($status['_101_2Started'] && $status['_101_2Ended']) {
            $status['available101Count']--;
        }
    }

    $response->getBody()->write(json_encode($status));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/api/branches', function (Request $request, Response $response, $args) {
    $branches = Capsule::table('branches')->get();
    $response->getBody()->write(json_encode($branches));
    return $response->withHeader('Content-Type', 'application/json');
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

$app->get('/api/employee-branch/{id}', function (Request $request, Response $response, $args) {
    $employeeId = $args['id'];

    $employeeBranch = Capsule::table('employees')
        ->join('branches', 'employees.sucursal_id', '=', 'branches.id')
        ->where('employees.id', $employeeId)
        ->select('branches.id', 'branches.nombre', 'branches.latitud', 'branches.longitud')
        ->first();

    if (!$employeeBranch) {
        return respondWithError($response, 'Empleado o sucursal no encontrada', 404);
    }

    $response->getBody()->write(json_encode($employeeBranch));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->map(["GET", "POST", "PUT", "DELETE", "PATCH"], "/{routes:.+}", function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();

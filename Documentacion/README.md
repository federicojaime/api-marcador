# API Sistema de Control de Asistencia

## Información General

Esta API REST está desarrollada en PHP usando el framework Slim v4 y proporciona funcionalidades para el control de asistencia de empleados mediante reconocimiento facial, validación de PIN y geolocalización.

### Tecnologías Utilizadas
- **Framework**: Slim v4
- **Base de datos**: MySQL con Eloquent ORM (Laravel)
- **Autenticación**: JWT (JSON Web Tokens)
- **Reconocimiento facial**: Descriptores faciales con validación euclidiana
- **Geolocalización**: Validación de proximidad al lugar de trabajo
- **Email**: PHPMailer para notificaciones

### URL Base
```
{base_url}/api/
```

## Autenticación

La API utiliza JWT (JSON Web Tokens) para la autenticación. La mayoría de endpoints requieren un token válido en el header:

```
Authorization: Bearer {token}
```

### Endpoints sin autenticación:
- Todos los endpoints de fichaje (`/check_in`, `/check_out`, etc.)
- Endpoints de información (`/employees`, `/branches`, `/employee-status`, `/employee-branch`)

## Endpoints de Fichaje

### 1. Entrada (Check In)
Registra la entrada del empleado al trabajo.

**POST** `/api/check_in`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Entrada fichada correctamente"
}
```

**Errores posibles**:
- `400`: Datos incompletos
- `401`: Descriptor facial no coincide / PIN incorrecto
- `403`: Demasiado lejos del lugar de trabajo
- `404`: Empleado no encontrado

### 2. Salida (Check Out)
Registra la salida del empleado del trabajo.

**POST** `/api/check_out`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Salida fichada correctamente"
}
```

### 3. Inicio de Almuerzo
Registra el inicio del período de almuerzo.

**POST** `/api/lunch_start`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Inicio de almuerzo registrado correctamente"
}
```

### 4. Fin de Almuerzo
Registra el fin del período de almuerzo.

**POST** `/api/lunch_end`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Fin de almuerzo registrado correctamente"
}
```

### 5. Fichaje de Bono
Registra un fichaje especial de bono.

**POST** `/api/bonus_check_in`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Bono fichado correctamente"
}
```

### 6. Inicio de 101
Registra el inicio de un período 101 (máximo 2 por día).

**POST** `/api/start_101`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Inicio del primer 101 registrado correctamente"
}
```

### 7. Fin de 101
Registra el fin de un período 101.

**POST** `/api/end_101`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Fin del primer 101 registrado correctamente"
}
```

### 8. Inicio de Hora Extra
Registra el inicio de horas extra.

**POST** `/api/overtime_start`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Inicio de hora extra registrado correctamente"
}
```

### 9. Fin de Hora Extra
Registra el fin de horas extra.

**POST** `/api/overtime_end`

**Body (JSON)**:
```json
{
  "descriptor": [array_de_numeros],
  "pin": "1234",
  "latitude": -84.123456,
  "longitude": 9.987654,
  "employee_id": 1
}
```

**Respuesta exitosa (200)**:
```json
{
  "message": "Fin de hora extra registrado correctamente"
}
```

## Endpoints de Consulta

### 1. Estado del Empleado
Obtiene el estado actual de fichajes del empleado para el día actual.

**GET** `/api/employee-status/{id}`

**Parámetros**:
- `id` (path): ID del empleado

**Respuesta exitosa (200)**:
```json
{
  "checkedIn": true,
  "checkedOut": false,
  "bonusCheckedIn": false,
  "lunchStarted": true,
  "lunchEnded": false,
  "_101Started": false,
  "_101Ended": false,
  "_101_2Started": false,
  "_101_2Ended": false,
  "overtimeStarted": false,
  "overtimeEnded": false,
  "available101Count": 2,
  "branchLatitude": 9.987654,
  "branchLongitude": -84.123456
}
```

### 2. Lista de Sucursales
Obtiene todas las sucursales disponibles.

**GET** `/api/branches`

**Respuesta exitosa (200)**:
```json
[
  {
    "id": 1,
    "nombre": "Sucursal Centro",
    "latitud": 9.987654,
    "longitud": -84.123456
  },
  {
    "id": 20,
    "nombre": "Sucursal Buenos Aires",
    "latitud": -34.603722,
    "longitud": -58.381592
  }
]
```

### 3. Lista de Empleados
Obtiene información básica de todos los empleados (incluyendo descriptores faciales).

**GET** `/api/employees`

**Respuesta exitosa (200)**:
```json
[
  {
    "id": 1,
    "nombre": "Juan Pérez",
    "descriptors": [
      [0.123, 0.456, 0.789, ...],
      [0.321, 0.654, 0.987, ...]
    ]
  }
]
```

### 4. Sucursal del Empleado
Obtiene información de la sucursal asignada a un empleado específico.

**GET** `/api/employee-branch/{id}`

**Parámetros**:
- `id` (path): ID del empleado

**Respuesta exitosa (200)**:
```json
{
  "id": 1,
  "nombre": "Sucursal Centro",
  "latitud": 9.987654,
  "longitud": -84.123456
}
```

## Endpoints de Usuarios (con JWT)

### 1. Login
Autentica un usuario y devuelve un token JWT.

**POST** `/user/login`

**Body (JSON)**:
```json
{
  "email": "usuario@example.com",
  "password": "contraseña"
}
```

**Respuesta exitosa (200)**:
```json
{
  "ok": true,
  "msg": "Usuario autorizado.",
  "data": {
    "id": 1,
    "firstname": "Juan",
    "lastname": "Pérez",
    "email": "usuario@example.com",
    "jwt": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

### 2. Registro de Usuario
Crea un nuevo usuario (requiere confirmación por email).

**POST** `/user/register`

**Body (JSON)**:
```json
{
  "email": "nuevo@example.com",
  "firstname": "Nombre",
  "lastname": "Apellido",
  "password": "contraseña"
}
```

### 3. Lista de Usuarios
Obtiene todos los usuarios registrados.

**GET** `/users`
*Requiere JWT*

### 4. Usuario por ID
Obtiene información de un usuario específico.

**GET** `/user/{id}`
*Requiere JWT*

### 5. Recuperación de Contraseña
Inicia el proceso de recuperación de contraseña.

**POST** `/user/password/recover`

**Body (JSON)**:
```json
{
  "email": "usuario@example.com"
}
```

## Validaciones de Seguridad

### 1. Reconocimiento Facial
- Utiliza descriptores faciales con distancia euclidiana
- Umbral de coincidencia: 0.5
- Cada empleado puede tener múltiples descriptores registrados

### 2. Validación de PIN
- El PIN se almacena hasheado usando bcrypt
- Se verifica usando `password_verify()`

### 3. Geolocalización
- Radio máximo permitido: 1 km del lugar de trabajo
- Cálculo usando fórmula de Haversine
- Coordenadas específicas por sucursal

### 4. Zonas Horarias
- Costa Rica: UTC-6 (por defecto)
- Argentina: UTC-3 (sucursal ID 20)
- Ajuste automático según la sucursal del empleado

## Reglas de Negocio

### Fichajes Diarios
- **Entrada**: Solo una por día
- **Salida**: Requiere entrada previa
- **Almuerzo**: Inicio requiere entrada, fin requiere inicio
- **Bono**: Solo uno por día
- **101**: Máximo 2 períodos por día
- **Hora Extra**: Un período por día

### Estructura de Base de Datos

#### Tabla `employees`
- `id`: Identificador único
- `nombre`: Nombre del empleado
- `email`: Email del empleado
- `sucursal_id`: ID de la sucursal asignada
- `descriptors`: JSON con descriptores faciales
- `pin`: PIN hasheado para autenticación

#### Tabla `attendance`
- `id`: Identificador único
- `employee_id`: Referencia al empleado
- `check_in`: Timestamp de entrada
- `check_out`: Timestamp de salida
- `lunch_start`: Timestamp de inicio de almuerzo
- `lunch_end`: Timestamp de fin de almuerzo
- `bonus_check_in`: Timestamp de fichaje de bono
- `start_101`: Timestamp de inicio del primer 101
- `end_101`: Timestamp de fin del primer 101
- `start_101_2`: Timestamp de inicio del segundo 101
- `end_101_2`: Timestamp de fin del segundo 101
- `overtime_start`: Timestamp de inicio de hora extra
- `overtime_end`: Timestamp de fin de hora extra

#### Tabla `branches`
- `id`: Identificador único
- `nombre`: Nombre de la sucursal
- `latitud`: Coordenada de latitud
- `longitud`: Coordenada de longitud

## Códigos de Error

- **200**: Operación exitosa
- **400**: Datos incompletos o formato incorrecto
- **401**: Credenciales inválidas (PIN, descriptor facial)
- **403**: Acceso denegado (ubicación, horario)
- **404**: Recurso no encontrado (empleado, sucursal)
- **409**: Conflicto (ya fichado, operación no permitida)
- **500**: Error interno del servidor

## Variables de Entorno

```env
JWT_SECRET_KEY=clave_secreta_jwt
JWT_ALGORITHM=HS256
SMTP_HOST=smtp.servidor.com
SMTP_PORT=465
SMTP_USERNAME=usuario@servidor.com
SMTP_PASSWORD=contraseña_smtp
SMTP_SENDER_NAME="Sistema de Asistencia"
APP_URL=https://tu-dominio.com/
```

## Instalación y Configuración

1. **Dependencias**:
   ```bash
   composer install
   ```

2. **Configurar variables de entorno**:
   - Copiar `.env.example` a `.env`
   - Configurar credenciales de base de datos y SMTP

3. **Base de datos**:
   - Importar estructura de tablas
   - Configurar conexión en `index.php`

4. **Servidor web**:
   - Configurar `.htaccess` para rewrite rules
   - Apuntar document root al directorio raíz

## Notas Importantes

- Todos los timestamps se manejan en la zona horaria de la sucursal
- Los descriptores faciales son arrays de números decimales
- La validación geográfica usa un radio de 1km
- El sistema soporta múltiples sucursales con diferentes zonas horarias
- Los empleados solo pueden fichar en su sucursal asignada
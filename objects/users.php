<?php
	namespace objects;

	use objects\Base;

	class Users extends Base {
		private $table_name = "users";

		// constructor
		public function __construct($db) {
			parent::__construct($db);
		}

		public function getUsers() {
			$query = "SELECT id, email, firstname, lastname FROM $this->table_name ORDER BY id";
			parent::getAll($query);
			return $this;
		}

		public function getUser($id) {
			$query = "SELECT id, email, firstname, lastname FROM $this->table_name WHERE id = :id";
			parent::getOne($query, ["id" => $id]);
			return $this;
		}

		public function getUserTemp($token) {
			$query = "SELECT * FROM userstemp WHERE token = :token";
			parent::getOne($query, ["token" => $token]);
			return $this;
		}

		public function getUserPasswordTemp($token) {
			$query = "SELECT * FROM passrecovery WHERE token = :token";
			parent::getOne($query, ["token" => $token]);
			return $this;
		}

		public function setNewPassword($id, $password) {
			$query = "UPDATE $this->table_name SET password = :password WHERE id = :id";
			$newPassword = password_hash($password, PASSWORD_BCRYPT);
			parent::update($query, ["id" => $id, "password" => $newPassword]);
			return $this;
		}

		public function deleteTempPassword($id) {
			$query = "DELETE FROM passrecovery WHERE id = :id";
			parent::update($query, ["id" => $id]);
			return $this;
		}

		public function moveTempUser($id) {
			$query = <<<EOD
			INSERT INTO users (email, firstname, lastname, password)
			SELECT
				ut.email,
				ut.firstname,
				ut.lastname,
				ut.password
			FROM userstemp ut
			WHERE
				ut.id = :id AND
				NOT EXISTS (
					SELECT 1 FROM users e WHERE e.email = ut.email
				);
			EOD;
			parent::add($query, ["id" => $id]);
			if(parent::getResult()->ok) {
				$query = "DELETE FROM userstemp WHERE id = :id";
				parent::delete($query, ["id" => $id]);
			}
			return $this;
		}

		public function setUser($values) {
			$query = "INSERT INTO $this->table_name SET email = :email, firstname = :firstname, lastname = :lastname, password = :password";
			$values["password"] = password_hash($values["password"], PASSWORD_BCRYPT);
			parent::add($query, $values);
			return $this;
		}

		public function updateUser($values) {
			$query = "INSERT INTO $this->table_name SET email = :email, firstname = :firstname, lastname = :lastname, password = :password WHERE id = :id";
			parent::update($query, $values);
			return $this;
		}

		public function deleteUser($id) {
			$query = "DELETE FROM $this->table_name WHERE id = :id";
			parent::delete($query, ["id" => $id]);
			return $this;
		}

		public function userExist($email) {
			$query = "SELECT * FROM $this->table_name WHERE email = :email";
			parent::getOne($query, ["email" => $email]);
			$userE = parent::getResult();
			if($userE->ok) {
				return $userE->data;
			}
			return false;
		}

		public function setRegister($values) {
			$query = "INSERT INTO userstemp SET email = :email, firstname = :firstname, lastname = :lastname, password = :password, token = :token, fecha = '" . date("Y-m-d") . "'";
			$values["password"] = password_hash($values["password"], PASSWORD_BCRYPT);
			parent::add($query, $values);
			return $this;
		}

		public function setTempRecovery($values) {
			$resp = new \stdClass();
			$existe = $this->userExist($values["email"]);
			if($existe) {
				$query = "INSERT INTO passrecovery SET iduser = :iduser, email = :email, token = :token, fecha = :fecha";
				$data = [
					"iduser" => $existe->id,
					"email" => $existe->email,
					"token" => $values["token"],
					"fecha" => date("Y-m-d")
				];
				parent::add($query, $data);
				$dataResp = parent::getResult();
				if($dataResp->ok) {
					$resp->ok = true;
					$resp->msg = "";
					$resp->data = [
						"newId" => $dataResp->data["newId"],
						"email" => $values["email"],
						"token" => $values["token"]
					];
					$resp->errores = [];
				} else {
					$resp = $dataResp;
				}
			} else {
				$resp->ok = false;
				$resp->msg = "La dirección de correo eletrónico " . $values["email"] . " no esta registrado en el sistema.";
				$resp->data = "";
				$resp->errores = [];
			}
			return $resp;
		}
	}
?>
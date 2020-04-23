<?php

namespace Guebbit\Auth;

use Guebbit\Base;
use NilPortugues\Sql\QueryBuilder\Builder\MySqlBuilder;
use NilPortugues\Sql\QueryBuilder\Builder\GenericBuilder;


class Users extends Base{
	// ----------- costants -----------
	//self::CONSTANT_NAME
	public const ERROR_WRONG_LOGIN 			= "Wrong username or password";
	public const ERROR_TAKEN_LOGIN 			= "Username or Email already taken";
	public const ERROR_WRONG_SECRET 		= "Wrong secret key";

	/**
	*	Da costruire un vero query builder (tipo codeigniter?)
	*	TODO where OR or AND (problema riutilizzo e chiarezza)
	*	@param string $table: nome tabella
	*	@param array $data: array di campi
	*	@param array $where: array associativo di campi e valori
	*	@param string $type: OR o AND
	**/
	protected function create_query_select($table, $data=false, $where=false, $type="AND"){
		$builder = new GenericBuilder();
		$query = $builder->select()->setTable($table);

		if(!empty($data))
			$query->setColumns($data);
		if(!empty($where))
			foreach($where as $column => $record)
				$query->where($type)->equals($column, $record);

		return [$builder->write($query), $builder->getValues()];
	}
	/**
	*	@param array $data: array associativo di campi e valori
	**/
	protected function create_query_update($table, $data, $where=false, $type="AND"){
		$builder = new GenericBuilder();
		$query = $builder->update()->setTable($table);

		$query->setValues($data);
		if(!empty($where))
			foreach($where as $column => $record)
				$query->where($type)->equals($column, $record);

		return [$builder->write($query), $builder->getValues()];
	}
	/**
	*	@param array $data: array associativo di campi e valori
	**/
	protected function create_query_insert($table, $data){
		$builder = new GenericBuilder();
		$query = $builder->insert()->setTable($table);
		$query->setValues($data);
		return [$builder->write($query), $builder->getValues()];
	}
	/**
	*	@param string $query
	*	@param array $params
	**/
	protected function execute_query($query, $params){
		$result = $this->cn->prepare($query);
		//foreach($params as $placeholder => $record)
		//	$result->bindParam($placeholder, $record);
		$result->execute($params);
		$temp_error = $this->cn->errorInfo();
		if(!is_null($temp_error[2]))
			return self::ERROR_DATABASE_EXPLAIN.": ".$temp_error[2];
		return $result;
	}






	public function insert_user($email, $username, $password){
		list($query, $params) = $this->create_query_insert("users", [
			"email" => $email,
			"username" => $username,
			"password" => $this->hash_password($password),
			"secret_key" => $this->hash_secretkey(),
			"level" => 2
		]);
		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return [false, self::ERROR_DATABASE_EXPLAIN.": ".$result];
		return [true, $this->cn->lastInsertId()];
	}
	public function insert_user_anon($username){
		list($query, $params) = $this->create_query_insert("users", [
			"username" => $username
		]);
		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return [false, self::ERROR_DATABASE_EXPLAIN.": ".$result];
		return [true, $this->cn->lastInsertId()];
	}
	public function insert_user_smart($id, $email, $username, $password){
		list($query, $params) = $this->create_query_update("users", [
			"email" => $email,
			"username" => $username,
			"password" => $this->hash_password($password),
			"secret_key" => $this->hash_secretkey(),
			"level" => 2
		], [
			"id" => $id
		]);
		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return [false, self::ERROR_DATABASE_EXPLAIN.": ".$result];
		return [true, $id];
	}



	//TODO public function update_user(){}



	public function find_user_byid($id){
		list($query, $params) = $this->create_query_select("users", false, [
			"id" => $id
		]);
		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return false;
		if($result->rowCount() < 1)
			return false;
		return $result->fetch(\PDO::FETCH_ASSOC);
	}


	public function check_user_credentials($credential, $password){
		list($query, $params) = $this->create_query_select("users", false, [
			"username" => $credential,
			"email" => $credential
		], "OR");

		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return self::ERROR_DATABASE_EXPLAIN.": ".$result;
		if($result->rowCount() < 1)
			return self::ERROR_WRONG_LOGIN;
		$row = $result->fetch(\PDO::FETCH_ASSOC);
		//password non corretta
		if(!$this->password_verify($password, $row["password"]))
			return self::ERROR_WRONG_LOGIN;

		return $row;
	}
	public function check_user_secret($secret){
		list($query, $params) = $this->create_query_select("users", false, [
			"secret_key" => $secret
		]);

		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return false;
		if($result->rowCount() < 1)
			return false;

		return $result->fetch(\PDO::FETCH_ASSOC);
	}
	public function check_user_exist($username, $email){
		list($query, $params) = $this->create_query_select("users", false, [
			"username" => $username,
			"email" => $email
		], "OR");
		$result = $this->execute_query($query, $params);
		if(is_string($result))
			return false;
		if($result->rowCount() < 1)
			return false;
		return true;
	}





	protected function hash_secretkey(){
		return $this->secure_hash(12);
	}
	protected function hash_password($password){
		return password_hash($this->sanitize($password), PASSWORD_DEFAULT, ['cost'=>12]);
	}
	protected function password_verify($plain_password, $hash_password){
		return password_verify($plain_password, $hash_password);
	}
}

<?php

class AccountModel {

	private $db;

	public function __construct() {
		$this->db = new Database();
	}

	/**
	 * Return user entries using current session username
	 * 
	 * @return: user entries
	 */
	public function getCurrentUser() {
		$this->db->query("SELECT * FROM users WHERE user_name = :username");
		$this->db->bind(':username', $_SESSION['user_username']);
		return $this->db->single();
	}

	/**
	 * Update user entries specified by user id
	 * 
	 * @param: associative array of columns name, username, email, id
	 * 
	 * @return: true on success, else false
	 */
	public function updateProfile($data) {
		$this->db->query('UPDATE users SET user_name = :name, user_username = :username,
			user_email = :email WHERE user_id = :id');

		$this->db->bind(':name', $data['name']);
		$this->db->bind(':username', $data['username']);
		$this->db->bind(':email', $data['email']);
		$this->db->bind(':id', $data['id']);

		if($this->db->execute()){
			return true;
		}else{
			return false;
		}

	}

	/**
	 * Retuns user entries associated to email
	 * 
	 * @param: email
	 */
	public function findUserByEmail($email) {
		$this->db->query("SELECT * FROM users WHERE user_email = :email");
		$this->db->bind(":email", $email);
		$row = $this->db->single();
		if($this->db->rowCount() > 0){
			return true;
		}else{
			return false;
		}
	}

	/**
	 * Get recipes user created
	 * User id selected by session
	 * 
	 * @return: array of recipe entries
	 */
	public function getUserRecipes() {
		$this->db->query("SELECT * FROM recipes WHERE ownerid = :id");
		$this->db->bind(":id", $_SESSION['user_id']);
		return $this->db->resultSet(true);
	}

	/**
	 * Deletes recipe by rid and all ingredients and directions. 
	 * Also removes file from file system of the uploaded image
	 * 
	 * @param: rid
	 * 
	 * @return: true on success
	 * @return: false on fail
	 */
	public function deleteRecipe($rid) {
		$this->db->query("SELECT imagePath FROM recipes WHERE rid=:rid");
		$this->db->bind(":rid", $rid);
		$target = $this->db->single();

		$this->db->query("DELETE FROM recipes WHERE rid=:rid");
		$this->db->bind(":rid", $rid);

		if($this->db->execute()){
			// Remove image if not placeholder
			if($target->imagePath != '/upload/placeholder.jpg') {
				unlink(dirname(APPROOT) . '/public/img' . $target->imagePath);
			}
			return true;
		}else{
			return false;
		}
	}

}

<?php

class Bookstore {
	public function __construct($db) {
		$this->db = $db;
	}

	public function read_books() {
		$rslt = $this->db->query("
SELECT id, title, price FROM book;
");
		$books = $rslt->fetch_all(MYSQLI_ASSOC);
		return ['status' => 200, 'content' => [
			'message' => 'ok',
			'books' => $books,
		]];
	}

	public function create_book($args) {
		$stmt = $this->db->prepare("
INSERT INTO book (title, price) VALUES (?, ?);
");
		$stmt->bind_param('sd', $args['title'], $args['price']);
		$stmt->execute();
		if ($stmt->error)
			database_error();

		$id = $stmt->insert_id;

		$stmt->close();

		return ['status' => 200, 'content' => [
			'message' => 'ok',
			'id' => $id,
		]];
	}

	public function delete_book($id) {
		$stmt = $this->db->prepare("
DELETE FROM book WHERE id = ?;
");
		$stmt->bind_param('i', $id);
		$stmt->execute();
		if ($stmt->error)
			database_error();
		if ($stmt->affected_rows != 1)
			return ['status' => 404, 'content' => [
				'message' => 'nonexist book'
			]];
		$stmt->close();
		return ['status' => 200, 'content' => [
			'message' => 'removed',
			'id' => $id,
		]];
	}

	public function update_book($id, $args) {
		//$this->db->begin_transaction();
		/*
		$stmt = $this->db->prepare("
SELECT COUNT(*) FROM book WHERE id = ?;
");
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$stmt->bind_result($cnt);
		$stmt->fetch();
		if ($cnt != 1)
			return ['status' => 404, 'content' => [
				'message' => 'nonexist book'
			]];
		$stmt->close();
		*/
		$stmt = $this->db->prepare("
UPDATE book SET title = ?, price = ? WHERE id = ?;
");
		$stmt->bind_param('sdi', $args['title'], $args['price'], $id);
		$stmt->execute();
		if ($stmt->error)
			database_error();
		if ($stmt->affected_rows != 1)
			return ['status' => 404, 'content' => [
				'message' => 'nonexist book'
			]];
		$stmt->close();
		//$this->db->commit();
		return ['status' => 200, 'content' => [
			'message' => 'ok',
			'id' => $id,
		]];
	}
}

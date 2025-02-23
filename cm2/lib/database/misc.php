<?php

require_once __DIR__ .'/database.php';

class cm_misc_db {

	public cm_db $cm_db;

	public function __construct(cm_db $cm_db) {
		$this->cm_db = $cm_db;
		$this->cm_db->table_def('config_misc', (
			'`key` VARCHAR(255) NOT NULL PRIMARY KEY,'.
			'`value` TEXT NULL'
		));
		$this->cm_db->table_def('config_misc_files', (
			'`file_name` VARCHAR(255) NOT NULL PRIMARY KEY,'.
			'`mime_type` VARCHAR(255) NULL,'.
			'`image_w` INT NULL,'.
			'`image_h` INT NULL,'.
			'`data` LONGBLOB NULL'
		));
	}

	public function getval(string $key, string $default): string
	{
		$stmt = $this->cm_db->execute(
			'SELECT `value` FROM `config_misc` WHERE `key` = ?'
			, [$key]
		);
		$row = $stmt->fetch(PDO::FETCH_NUM);
		return $row ? $row[0] : $default;
	}

	// TODO (Mr. Metric): This is MySQL-specific and needs to be rewritten later
	public function setval(string $key, string $value): void
	{
		$this->cm_db->execute(
			'INSERT INTO `config_misc` (`key`, `value`)'
			.' VALUES (?, ?)'
			.' ON DUPLICATE KEY UPDATE `value` = ?'
			, [$key, $value, $value]
		);
	}

	// The returned bool indicates whether or not a row was deleted.
	public function clearval(string $key): bool
	{
		$stmt = $this->cm_db->execute(
			'DELETE FROM `config_misc` WHERE `key` = ?'
			, [$key]
		);
		return $stmt->rowCount() !== 0;
	}

	public function upload_file(string $name, $type, $image_w, $image_h, $file): bool
	{
		if(!$type || !$file)
		{
			return false;
		}

		if($this->cm_db->table_has_row('config_misc_files', 'file_name', $name))
		{
			$sql = 'UPDATE `config_misc_files` SET'
				.' `file_name` = :file_name, `mime_type` = :mime_type, `image_w` = :image_w, `image_h` = :image_h, `data` = :data'
				.' WHERE `file_name` = :file_name';
		}
		else
		{
			$sql = 'INSERT INTO `config_misc_files`'
				.' (`file_name`, `mime_type`, `image_w`, `image_h`, `data`)'
				.' VALUES (:file_name, :mime_type, :image_w, :image_h, :data)';
		}

		$data = file_get_contents($file);
		if($data === false)
		{
			return false;
		}

		return $this->cm_db->prepare($sql)->execute([
			':file_name' => $name   ,
			':mime_type' => $type   ,
			':image_w'   => $image_w,
			':image_h'   => $image_h,
			':data'      => $data   ,
		]);
	}

	public function download_file(string $name, bool $attachment = false): bool
	{
		$stmt = $this->cm_db->execute(
			'SELECT `mime_type`, `data` FROM `config_misc_files` WHERE `file_name` = ?'
			, [$name]
		);
		$stmt->bindColumn(1, $type);
		$stmt->bindColumn(2, $data);
		if(!$stmt->fetch() || !$type || !$data)
		{
			return false;
		}

		if($attachment)
		{
			if(!strrpos($name, '.'))
			{
				$o = strrpos($type, '/');
				if($o)
				{
					$name .= '.' . substr($type, $o + 1);
				}
			}
			header('Content-Disposition: attachment; filename=' . $name);
		}

		header('Content-Type: ' . $type);
		header('Pragma: no-cache');
		header('Expires: 0');
		echo $data;

		return true;
	}

	public function get_file_image_size(string $name): array|false
	{
		$stmt = $this->cm_db->execute(
			'SELECT `image_w`, `image_h` FROM `config_misc_files` WHERE `file_name` = ?'
			, [$name]
		);
		$stmt->bindColumn(1, $image_w);
		$stmt->bindColumn(2, $image_h);
		if(!$stmt->fetch() || is_null($image_w) || is_null($image_h))
		{
			return false;
		}
		return [$image_w, $image_h];
	}

	public function delete_file(string $name): void
	{
		$this->cm_db->execute(
			'DELETE FROM `config_misc_files` WHERE `file_name` = ?'
			, [$name]
		);
	}


	public function getBadgeTypesFromQuestionAnswer(string $creditId, string $approvalId): array
	{
		return $this->cm_db->execute(
			"SELECT attendees.badge_type_id AS badge_id,
				attendee_badge_types.name AS badge_name,
				form_credits.answer AS answer
			FROM `attendees`
				INNER JOIN `form_answers` AS form_credits
					ON form_credits.question_id = :credit_id
						AND form_credits.context = 'attendee'
						AND attendees.id = form_credits.context_id
				INNER JOIN `form_answers` AS forms_approval
					ON forms_approval.question_id = :approval_id
						AND forms_approval.context = 'attendee'
						AND attendees.id = forms_approval.context_id
				INNER JOIN `attendee_badge_types`
					ON attendees.badge_type_id = attendee_badge_types.id
			ORDER BY attendees.id"
		, [
			':credit_id'   => $creditId  ,
			':approval_id' => $approvalId,
		])->fetchAll(PDO::FETCH_ASSOC);
	}
}

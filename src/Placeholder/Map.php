<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Placeholder;

use Exception;
use Generator;
use Ultra\Data\Placeholder;
use Ultra\Data\Query;
use Ultra\Data\Query\Status;

class Map {
	private array $_placeholders;

	public function __construct(){
		$this->_placeholders = [];
	}

	public function add(string $index, string $type, bool $conditional): void {
		if (isset($this->_placeholders[$index])) {
			return;
		}

		if (!$placeholder = Type::tryFrom($type)) {
			Query::error(Status::UnexpectedPlaceholderType, $type, $index);
		}

		$this->_placeholders[$index] = new Placeholder($index, $placeholder, $conditional);
	}

	public function clean(): void {
		$this->_placeholders = [];
	}

	public function iterator(): Generator {
		foreach ($this->_placeholders as $id => $placeholder) {
			yield $id => $placeholder;
		}
	}
}
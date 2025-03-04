<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Query;

use Ultra\Condition;

enum Status: int implements Condition {
	case OK                 = 350;
	case NotEnoughValues    = 351;
	case TooManyValues      = 352;
	case UnknownPlaceholder = 353;

	public function isFatal(): bool {
		return false;
	}

	public function message(string ...$values): string {
		$message = $this->_message();

		if (count($values) > 0) {
			$search = [];
			$replace = [];

			foreach ($values as $key => $value) {
				$search[] = '{'.$key.'}';
				$replace[] = $value;
			}

			$message = str_replace($search, $replace, $message);
		}

		if (preg_match('/\{\d+\}/', $message) > 0) {
			$message = preg_replace('/\{\d+\}/', '...', $message);
		}

		return $message;
	}

	private function _message(): string {
		return match($this) {
			self::NotEnoughValues    => 'Not enough values for query "{0}". Expected {1} values, received {2}.',
			self::TooManyValues      => 'Too many values for query "{0}". Expected {1} values, received {2}.',
			self::UnknownPlaceholder => 'Query "{0}" contains unknown placeholder {1}.',
			self::OK                 => 'OK',
		};
	}
}

<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

use Closure;
use Ultra\Data\Placeholder\Map;
use Ultra\Data\Query\Statement;
use Ultra\Data\Query\Status;

class Query {
	private const string PLACEHOLDERS = '/(\{)? (\w+)? (
		(?<!\:|\}) \: (?!\:|\{) [abdfiknqsuvzABCDIKLNQSUV]? |
		(?<!\?|\}) \? (?!\?|\{) [abdinsuzABCDILNSU]? |
		(?<=\{)   \w+ (?=\})
	)(?(1)\})/ux';

	public readonly Map $map;
	public readonly Closure $booleans;
	public readonly string $start_quote;
	public readonly string $end_quote;
	private bool $_statement;
	private string $_query;
	private array $_marker;

	public function __construct(
		public readonly Closure $escape,
		public readonly string $constants = '/^@{0,2}[^\W\d]([\w\.\(\s]*(\w|\)))?$/',
		public readonly string $quantifiers = '/^[^\W\d]([\w\.]*\w)?$/u',
		bool $booleans = false,
		string $quotes = '`',
	) {
		$this->map = new Map();
		$this->_statement = false;
		$this->_query = '';
		$this->_marker = [];

		if ($booleans) {
			$this->booleans = fn($value) => $value ? 'TRUE' : 'FALSE';
		}
		else {
			$this->booleans = fn($value) => (string) (int) $value;
		}

		[$this->start_quote, $this->end_quote] = match(strlen($quotes)) {
			2 => str_split($quotes),
			1 => [$quotes, $quotes],
			default => ['', ''],
		};
	}

	public function statement(string $statement, string $marker = ''): void {
		$this->_setMarker($marker);
		$this->_make($statement);
	}

	public function list(string|int|float|bool|Closure|array|null ...$variables): string {
		return $this->_build($variables);
	}

	public function map(array $options): string {
		return $this->_build($options);
	}

	public function share(array $shared, string|int|float|bool|Closure|array|null ...$variables): string {
		return $this->_build(array_merge([$shared, $shared], $variables));
	}

	public function join(array $options): string {
		$this->_commonCheck($options);
		return $this->_build(array_merge([$options[0]], $options));
	}

	private function _build(array $vars): string {
		if (!$this->_statement) {
			return $this->_query;
		}

		return $this->_dropConditions($this->_fillPraceholders($this->_query, $vars));
	}

	private function _setMarker(string $marker): void {
		$this->_marker = str_split($marker);
		$this->_marker[0] ??= '';
		$this->_marker[1] ??= $this->_marker[0];
		$this->_marker[2] = preg_quote($this->_marker[0]);
		$this->_marker[3] = preg_quote($this->_marker[1]);
	}

	private function _make(string $statement): void {
		if (0 == preg_match_all(self::PLACEHOLDERS, $statement, $matches, PREG_OFFSET_CAPTURE)) {
			$this->_statement = false;
			$this->_query = $statement;
			return;
		}

		$sm = new Statement(
			holders:  array_map(fn($match) => $match[0], $matches[0]),
			captures: array_map(fn($match) => $match[1], $matches[0]),
			sequence: array_map(fn($match) => $match[0], $matches[2]),
			types:    array_map(fn($match) => $match[0], $matches[3]),
		);

		$this->_statement = true;
		$this->_query = $sm->buildQuery($statement, $this->_marker[2], $this->_marker[3]);
		$sm->buildMap($this->map);
	}

	private function _dropConditions(string $query): string {
		if (str_contains($query, '['.$this->_marker[0])) {
			if (preg_match_all('/\['.$this->_marker[2].'.+'.$this->_marker[3].'\]/U', $query, $matches)) {
				foreach ($matches[0] as $match) {
					if (preg_match('/\{\w+\}/', $match)) {
						$query = str_replace($match, '', $query);
					}
				}
			}			

            return str_replace(['['.$this->_marker[0], $this->_marker[1].']'], '', $query);
        }

		return $query;
	}

	private function _fillPraceholders(string $query, array $vars): string {
		$lack = [];

		foreach ($this->map->iterator() as $id => $placeholder) {
			if (isset($vars[$id])) {
				$query = $this->_assignPlaceholder($placeholder, $query, $vars[$id]);
			}
			elseif (!$placeholder->conditional) {
				$lack[] = '\''.$placeholder->index.$placeholder->type->value.'\'';
			}
		}

		if (isset($lack[0])) {
			Status::PlaceholdersWithoutValue->error($this->_query, implode(', ', $lack));
		}

		return $query;
	}

	private function _assignPlaceholder(Placeholder $placeholder, string $query, string|int|float|bool|Closure|array|null $var): string {
		$placeholder->assign($this, $var);
		$query = str_replace($placeholder->search, $placeholder->value, $query);
		$placeholder->flush();
		return $query;
	}

	private function _commonCheck(array $options): void {
		if (!isset($options[0])) {
			Status::MissingSharedValue->error($this->_query);
		}

		if (!is_array($options[0])) {
			Status::UnexpectedSharedValueType->error($this->_query, gettype($options[0]));
		}
	}
}

<?php namespace Atomino\Gold;

use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ListSorting extends Attr implements \JsonSerializable {
	public function __construct(public string $name, public \Closure $method, public string|null $label = null) {
		if(is_null($this->label)) $this->label = $name;
	}
	public function jsonSerialize() { return $this->label; }
}

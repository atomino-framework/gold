<?php namespace Atomino\Gold;

use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ListView extends Attr implements \JsonSerializable {
	public function __construct(public string $name, public \Closure $method, public string|null $label = null) {
		if (is_null($label)) $this->label = $this->name;
	}
	public function jsonSerialize() { return $this->label; }
}

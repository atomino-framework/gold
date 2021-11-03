<?php namespace Atomino\Gold;

use Atomino\Mercury\Responder\Api\Attributes\Auth;
use Atomino\Neutrons\Attr;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Goldify extends Attr {
	public Auth $auth;
	public function __construct(public string $class, string|bool|null $roles = null) {
		$this->auth = new Auth($roles);
	}
}

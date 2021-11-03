<?php namespace Atomino\Gold;

use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Api;

abstract class AbstractApi extends Api {
	protected Entity|string $entity;
	public function __construct(Gold $router) { $this->entity = $router->getEntity(); }
}

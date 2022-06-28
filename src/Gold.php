<?php

namespace Atomino\Gold;

use Atomino\Bundle\Authenticate\Authenticator;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Auth;
use Atomino\Mercury\Responder\Error;
use Atomino\Mercury\Router\Router;
use Symfony\Component\HttpFoundation\Response;
use function Atomino\debug;

class Gold extends Router {

	/** @var Entity */
	private string $entity;
	private Auth $auth;

	public function __construct() {
		$gold = Goldify::get(new \ReflectionClass($this));
		$this->entity = $gold->class;
		$this->auth = $gold->auth;
	}

	public final function getEntity() { return $this->entity; }

	protected function listApi(): ListApi { return new ListApi($this); }
	protected function itemApi(): ItemApi { return new ItemApi($this); }
	protected function attachmentApi(): AttachmentApi { return new AttachmentApi($this); }
	protected function collectionApi(): CollectionApi { return new CollectionApi($this); }
	protected function customApi(): Api|null { return null; }

	protected final function route(): void {
		if (!$this->auth->authCheck($this->container->get(Authenticator::class))) $this()->pipe(...Error::setup(Response::HTTP_UNAUTHORIZED));
		if (!$this->auth->roleCheck($this->container->get(Authenticator::class))) $this()->pipe(...Error::setup(Response::HTTP_FORBIDDEN));
		$this("POST", "/list/**")?->pipe($this->listApi());
		$this("POST", "/item/**")?->pipe($this->itemApi());
		$this("POST", "/attachment/**")?->pipe($this->attachmentApi());
		$this("POST", "/collection/**")?->pipe($this->collectionApi());
		if (!is_null($customApi = $this->customApi())) $this(path:"/**")?->pipe($customApi);
	}
}

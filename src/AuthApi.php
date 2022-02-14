<?php

namespace Atomino\Gold;

use Atomino\Bundle\Authenticate\SessionAuthenticator;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Auth;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use Symfony\Component\HttpFoundation\Response;
use function Atomino\debug;

abstract class AuthApi extends Api {

	public function __construct(protected SessionAuthenticator $authenticator) { }

	abstract public function getAuthenticated(): Entity|null;
	abstract public function getUserName(Entity $user): string;
	abstract public function getUserAvatar(Entity $user): string|null;
	abstract public function authorize(Entity $user): bool;

	#[Route(self::POST, '/get')]
	public final function POST_get() {
		$user = $this->getAuthenticated();
		if (is_null($user) || !$this->authorize($user)) {
			$this->logout();
			return null;
		}
		return [
			"id"     => $user->id,
			"name"   => $this->getUserName($user),
			"roles"  => $user->getRoles(),
			"avatar" => $this->getUserAvatar($user),
		];
	}

	#[Route(self::POST, '/login')]
	public final function POST_login() {
		$this->logout();
		$login = $this->data->get('login');
		$password = $this->data->get('password');
		if (!$this->authenticator->login($login, $password)) {
			$this->setStatusCode(Response::HTTP_UNAUTHORIZED);
			return false;
		}
		if (!$this->authorize($this->getAuthenticated())) {
			$this->setStatusCode(Response::HTTP_FORBIDDEN);
			$this->logout();
			return false;
		}
		return true;
	}

	#[Route(self::POST, '/logout')]
	#[Auth]
	public final function POST_logout() {
		$this->logout();
	}

	protected function logout() {
		if (!is_null($this->getAuthenticated())) $this->authenticator->logout($this->getResponse());
	}

}

<?php

namespace Atomino\Gold;

use Atomino\Bundle\Authenticate\SessionAuthenticator;
use Atomino\Carbon\Entity;
use Atomino\Mercury\Responder\Api\Api;
use Atomino\Mercury\Responder\Api\Attributes\Auth;
use Atomino\Mercury\Responder\Api\Attributes\Route;
use Symfony\Component\HttpFoundation\Response;

abstract class AuthApi extends Api {

	public function __construct(private SessionAuthenticator $authenticator) { }

	abstract public function getAuthenticated(): Entity|null;
	abstract public function getUserName(Entity $user): string;
	abstract public function getUserAvatar(Entity $user): string|null;

	#[Route(self::POST, '/get')]
	public final function POST_get() {
		$user = $this->getAuthenticated();
		if (is_null($user)) return null;
		return [
			"id"     => $user->id,
			"name"   => $this->getUserName($user),
			"roles"  => $user->getRoles(),
			"avatar" => $this->getUserAvatar($user),
		];
	}

	#[Route(self::POST, '/login')]
	#[Auth(false)]
	public final function POST_login() {
		$login = $this->data->get('login');
		$password = $this->data->get('password');
		if (!$this->authenticator->login($login, $password)) {
			$this->setStatusCode(Response::HTTP_UNAUTHORIZED);
			return false;
		}
		return true;
	}

	#[Route(self::POST, '/logout')]
	#[Auth]
	public final function POST_logout() {
		$this->authenticator->logout($this->getResponse());
	}

}

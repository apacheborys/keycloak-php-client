<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Http\Test;

use Apacheborys\KeycloakPhpClient\DTO\Request\CreateUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\DeleteUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\LoginUserDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\ResetUserPasswordDto;
use Apacheborys\KeycloakPhpClient\DTO\Request\SearchUsersDto;
use Apacheborys\KeycloakPhpClient\DTO\Response\RequestAccessDto;
use Apacheborys\KeycloakPhpClient\Http\KeycloakHttpClientInterface;
use LogicException;
use Override;
use Throwable;

final class TestKeycloakHttpClient implements KeycloakHttpClientInterface
{
    /**
     * @var array<string, list<mixed>>
     */
    private array $queues = [];

    /**
     * @var list<array{method: string, args: list<mixed>}>
     */
    private array $calls = [];

    public function queueResult(string $method, mixed $result): void
    {
        $this->queues[$method][] = $result;
    }

    /**
     * @return list<array{method: string, args: list<mixed>}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    #[Override]
    public function getUsers(SearchUsersDto $dto): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    #[Override]
    public function createUser(CreateUserDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function updateUser(string $userId, array $payload): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$userId, $payload]);

        return $result;
    }

    #[Override]
    public function deleteUser(DeleteUserDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function createRealm(array $payload): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$payload]);

        return $result;
    }

    #[Override]
    public function getRoles(): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: []);

        return $result;
    }

    #[\Override]
    public function deleteRole(string $role): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$role]);
    }

    #[Override]
    public function getJwks(string $realm): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$realm]);

        return $result;
    }

    #[Override]
    public function getAvailableRealms(): array
    {
        /** @var array $result */
        $result = $this->nextResult(method: __FUNCTION__, args: []);

        return $result;
    }

    #[Override]
    public function resetPassword(ResetUserPasswordDto $dto): void
    {
        $this->nextResult(method: __FUNCTION__, args: [$dto]);
    }

    #[Override]
    public function loginUser(LoginUserDto $dto): RequestAccessDto
    {
        /** @var RequestAccessDto $result */
        $result = $this->nextResult(method: __FUNCTION__, args: [$dto]);

        return $result;
    }

    /**
     * @param list<mixed> $args
     */
    private function nextResult(string $method, array $args): mixed
    {
        $this->calls[] = [
            'method' => $method,
            'args' => $args,
        ];

        if (empty($this->queues[$method])) {
            throw new LogicException("No queued result for {$method}()");
        }

        $result = array_shift($this->queues[$method]);

        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }
}

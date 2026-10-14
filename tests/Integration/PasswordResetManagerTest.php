<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\Exception\AuthenticationException;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Identity\Data\Event\PasswordReset;
use JardisSupport\Identity\Data\Event\PasswordResetRequested;
use JardisSupport\Identity\Exception\WeakPasswordException;
use JardisSupport\Identity\PasswordResetManager;
use JardisSupport\Identity\Tests\Support\Wiring;
use PHPUnit\Framework\TestCase;

final class PasswordResetManagerTest extends TestCase
{
    private Wiring $w;
    private PasswordResetManager $manager;
    private string $userId;

    protected function setUp(): void
    {
        $this->w = new Wiring();
        $this->manager = new PasswordResetManager(
            $this->w->users,
            $this->w->hasher,
            $this->w->issuer,
            $this->w->redeemer,
            $this->w->sessions,
        );
        $this->userId = $this->w->userManager->register('max@acme.test', 'secret-1', 'Max')->user->getId();
    }

    public function testMatrix09WrongTokenTypeIsRejected(): void
    {
        $access = $this->w->sessions->create(Subject::from($this->userId))->accessToken;

        try {
            $this->manager->reset($access, 'secret-2');
            self::fail('an access token must not reset a password');
        } catch (AuthenticationException) {
            self::assertTrue($this->w->hasher->verify('secret-1', (string) $this->w->users->passwordHashOf($this->userId)));
        }
    }

    public function testMatrix10TokenWorksOnlyOnce(): void
    {
        $request = $this->manager->request('max@acme.test');
        self::assertNotNull($request);
        $this->manager->reset($request->token, 'secret-2');

        $this->expectException(AuthenticationException::class);
        $this->manager->reset($request->token, 'secret-3');
    }

    public function testMatrix11ResetEndsAllSessions(): void
    {
        $access = $this->w->sessions->create(Subject::from($this->userId))->accessToken;
        $auth = new TokenAuthenticator($this->w->tokens);
        self::assertTrue($auth->authenticate(Credential::token($access))->isSuccess());

        $request = $this->manager->request('max@acme.test');
        self::assertNotNull($request);
        $this->manager->reset($request->token, 'secret-2');

        self::assertFalse($auth->authenticate(Credential::token($access))->isSuccess());
        self::assertTrue($this->w->hasher->verify('secret-2', (string) $this->w->users->passwordHashOf($this->userId)));
    }

    public function testMatrix12RequestForUnknownOrDisabledReturnsNullWithoutEvent(): void
    {
        self::assertNull($this->manager->request('nobody@acme.test'));

        $this->w->userManager->disable($this->userId);
        self::assertNull($this->manager->request('max@acme.test'));
        self::assertSame(0, (int) $this->w->connection->pdo()
            ->query("SELECT COUNT(*) FROM auth_tokens WHERE token_type = 'password_reset'")->fetchColumn());
    }

    public function testMatrix20RequestAndResetEmitEvents(): void
    {
        $request = $this->manager->request('MAX@acme.test');
        self::assertNotNull($request);
        $requested = $request->events[0];
        self::assertInstanceOf(PasswordResetRequested::class, $requested);
        self::assertSame($this->userId, $requested->userId);

        $event = $this->manager->reset($request->token, 'secret-2');
        self::assertInstanceOf(PasswordReset::class, $event);
        self::assertSame($this->userId, $event->userId);
    }

    public function testWeakNewPasswordIsRejected(): void
    {
        $request = $this->manager->request('max@acme.test');
        self::assertNotNull($request);

        $this->expectException(WeakPasswordException::class);
        $this->manager->reset($request->token, 'short');
    }
}

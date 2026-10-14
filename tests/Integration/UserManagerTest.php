<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\Exception\InvalidCredentialException;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Data\Identity;
use JardisSupport\Identity\Data\Event\PasswordChanged;
use JardisSupport\Identity\Data\Event\UserDisabled;
use JardisSupport\Identity\Data\Event\UserEnabled;
use JardisSupport\Identity\Data\Event\UserRegistered;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\Exception\EmailAlreadyRegisteredException;
use JardisSupport\Identity\Exception\InvalidEmailException;
use JardisSupport\Identity\Exception\UserNotFoundException;
use JardisSupport\Identity\Exception\WeakPasswordException;
use JardisSupport\Identity\Tests\Support\Connections;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\UserManager;
use PHPUnit\Framework\TestCase;

final class UserManagerTest extends TestCase
{
    private DbConnectionInterface $connection;
    private DbUserStore $users;
    private DbTokenStore $tokens;
    private PasswordHasher $hasher;
    private UserManager $manager;

    protected function setUp(): void
    {
        $this->connection = Connections::sqlite();
        IdentityTables::recreate($this->connection);
        $this->users = new DbUserStore($this->connection);
        $this->tokens = new DbTokenStore($this->connection);
        $this->hasher = PasswordHasher::argon2id(memoryCost: 8192, timeCost: 1);
        $this->manager = new UserManager($this->users, $this->hasher, new Identity(), new SessionManager($this->tokens));
    }

    public function testRegisterNormalizesStoresHashAndEmitsEvent(): void
    {
        $result = $this->manager->register('  Max@Example.TEST ', 'secret-pw', 'Max');

        self::assertSame('max@example.test', $result->user->getEmail());
        self::assertSame(UserStatus::Active, $result->user->getStatus());
        $hash = $this->users->passwordHashOf($result->user->getId());
        self::assertNotNull($hash);
        self::assertTrue($this->hasher->verify('secret-pw', $hash));
        self::assertCount(1, $result->events);
        $event = $result->events[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame($result->user->getId(), $event->userId);
        self::assertSame('max@example.test', $event->email);
        self::assertSame('UTC', $event->timestamp->getTimezone()->getName());
    }

    public function testDuplicateEmailInOtherSpellingIsRejected(): void
    {
        $this->manager->register('max@example.test', 'secret-pw', 'Max');

        $this->expectException(EmailAlreadyRegisteredException::class);
        $this->manager->register(' MAX@Example.test', 'secret-pw', 'Max Zwei');
    }

    public function testUnknownUserIsReportedByChangePasswordDisableAndEnable(): void
    {
        foreach ([
            fn () => $this->manager->changePassword('nope', 'a', 'secret-new'),
            fn () => $this->manager->disable('nope'),
            fn () => $this->manager->enable('nope'),
        ] as $call) {
            try {
                $call();
                self::fail('UserNotFoundException expected');
            } catch (UserNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testWrongCurrentPasswordIsRejectedAndHashStaysUnchanged(): void
    {
        $user = $this->manager->register('max@example.test', 'secret-pw', 'Max')->user;
        $before = $this->users->passwordHashOf($user->getId());

        try {
            $this->manager->changePassword($user->getId(), 'wrong-pw', 'secret-new');
            self::fail('InvalidCredentialException expected');
        } catch (InvalidCredentialException) {
            self::assertSame($before, $this->users->passwordHashOf($user->getId()));
        }
    }

    public function testChangePasswordReplacesHashAndEmitsEvent(): void
    {
        $user = $this->manager->register('max@example.test', 'secret-pw', 'Max')->user;

        $event = $this->manager->changePassword($user->getId(), 'secret-pw', 'secret-new');

        self::assertInstanceOf(PasswordChanged::class, $event);
        self::assertSame($user->getId(), $event->userId);
        $hash = (string) $this->users->passwordHashOf($user->getId());
        self::assertTrue($this->hasher->verify('secret-new', $hash));
        self::assertFalse($this->hasher->verify('secret-pw', $hash));
    }

    public function testInvalidEmailIsRejected(): void
    {
        $this->expectException(InvalidEmailException::class);
        $this->manager->register('not-an-email', 'secret-pw', 'Max');
    }

    public function testTooShortPasswordIsRejectedOnRegisterAndChange(): void
    {
        try {
            $this->manager->register('max@example.test', 'short', 'Max');
            self::fail('WeakPasswordException expected');
        } catch (WeakPasswordException) {
            self::assertNull($this->users->findByEmail('max@example.test'));
        }

        $user = $this->manager->register('max@example.test', 'secret-pw', 'Max')->user;
        $before = $this->users->passwordHashOf($user->getId());
        try {
            $this->manager->changePassword($user->getId(), 'secret-pw', 'short');
            self::fail('WeakPasswordException expected');
        } catch (WeakPasswordException) {
            self::assertSame($before, $this->users->passwordHashOf($user->getId()));
        }
    }

    public function testDisableEndsSessionsAndEnableReactivates(): void
    {
        $user = $this->manager->register('max@example.test', 'secret-pw', 'Max')->user;
        $sessions = new SessionManager($this->tokens);
        $session = $sessions->create(Subject::from($user->getId()));
        $authenticator = new TokenAuthenticator($this->tokens);
        self::assertTrue($authenticator->authenticate(Credential::token($session->accessToken))->isSuccess());

        $disabled = $this->manager->disable($user->getId());

        self::assertInstanceOf(UserDisabled::class, $disabled);
        self::assertSame($user->getId(), $disabled->userId);
        self::assertSame(UserStatus::Disabled, $this->users->findById($user->getId())?->getStatus());
        self::assertFalse(
            $authenticator->authenticate(Credential::token($session->accessToken))->isSuccess(),
            'disable must end the sessions issued before',
        );

        $enabled = $this->manager->enable($user->getId());

        self::assertInstanceOf(UserEnabled::class, $enabled);
        self::assertSame(UserStatus::Active, $this->users->findById($user->getId())?->getStatus());
    }
}

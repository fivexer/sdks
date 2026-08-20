<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Partial update of a worker identity. Revoking is a `status` change here, not a DELETE — the
 * worker stays routable, they just lose the ability to sign in.
 *
 * Unlike every other input model this one does not build its body with {@see \Fivexer\SDK\Internal\Json::compact()}.
 * Dropping nulls is the right rule almost everywhere, but `email` is genuinely nullable on this
 * endpoint: absent keeps the stored address, an explicit null erases it. Expressing "erase"
 * therefore needs its own flag, because `email(null)` cannot be told apart from never calling it.
 */
final class UpdateWorkerIdentity
{
    private bool $clearingEmail = false;

    public function __construct(
        private ?string $label = null,
        private ?string $email = null,
        private ?string $pin = null,
        /** 'active' | 'revoked' */
        private ?string $status = null,
    ) {
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function pin(string $pin): self
    {
        $this->pin = $pin;
        return $this;
    }

    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /** Set a new address. Use {@see self::clearEmail()} to erase the stored one instead. */
    public function email(string $email): self
    {
        $this->email = $email;
        $this->clearingEmail = false;
        return $this;
    }

    /** Erase the stored email address, sending an explicit null. */
    public function clearEmail(): self
    {
        $this->email = null;
        $this->clearingEmail = true;
        return $this;
    }

    /**
     * Serialise for the wire, omitting unset fields but preserving an explicit email null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [];
        if ($this->label !== null) {
            $payload['label'] = $this->label;
        }
        if ($this->pin !== null) {
            $payload['pin'] = $this->pin;
        }
        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }
        if ($this->email !== null) {
            $payload['email'] = $this->email;
        } elseif ($this->clearingEmail) {
            $payload['email'] = null;
        }
        return $payload;
    }
}

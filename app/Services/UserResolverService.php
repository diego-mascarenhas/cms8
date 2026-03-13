<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Str;

class UserResolverService
{
    /**
     * Resolve the user_id to use for an agent conversation from phone and/or contact.
     * If no user exists for the contact/phone, creates one so conversations can be associated.
     *
     * @param  string|null  $phone  Phone number (e.g. WhatsApp, can include prefix)
     * @param  int|null  $contactId  Optional contact ID to resolve via contact.user_id or contact data
     * @return User|null The user to use for the conversation, or null if unable to resolve (e.g. no auth)
     */
    public function resolveUserForConversation(?string $phone = null, ?int $contactId = null): ?User
    {
        $user = null;

        if ($phone !== null && $phone !== '')
        {
            $user = $this->findUserByPhone($phone);
        }

        if ($user === null && $contactId !== null)
        {
            $user = $this->findUserByContactId($contactId);
        }

        if ($user === null && $phone !== null && $phone !== '')
        {
            $user = $this->createUserForPhone($phone);
        }

        if ($user === null && $contactId !== null)
        {
            $contact = Contact::withoutGlobalScopes()->find($contactId);
            if ($contact)
            {
                $user = $this->createUserForContact($contact);
            }
        }

        return $user;
    }

    /**
     * Find user by phone: direct User.phone or via Contact (sources or phone field).
     */
    protected function findUserByPhone(string $phoneNumber): ?User
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if ($cleanNumber === '')
        {
            return null;
        }

        $user = User::withoutGlobalScopes()->where('phone', $cleanNumber)->first();
        if ($user)
        {
            return $user;
        }

        if (strlen($cleanNumber) > 9)
        {
            $withoutCountryCode = substr($cleanNumber, -9);
            $user = User::withoutGlobalScopes()->where('phone', $withoutCountryCode)->first();
            if ($user)
            {
                return $user;
            }
        }

        $contact = Contact::with('user')
            ->whereHas('sources', function ($query) use ($cleanNumber)
            {
                $query->where('source_id', 2)->where('value', $cleanNumber);
            })->first();

        if ($contact && $contact->user)
        {
            return $contact->user;
        }

        $contact = Contact::where(function ($q) use ($cleanNumber)
        {
            $q->where('phone', 'like', '%'.$cleanNumber.'%')
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', '') = ?", [$cleanNumber]);
        })->first();

        if ($contact && $contact->user_id)
        {
            return User::withoutGlobalScopes()->find($contact->user_id);
        }

        return null;
    }

    /**
     * Find user by contact ID (contact.user_id).
     */
    protected function findUserByContactId(int $contactId): ?User
    {
        $contact = Contact::withoutGlobalScopes()->with('user')->find($contactId);

        return $contact?->user;
    }

    /**
     * Create a minimal User for a phone number so conversations can be associated.
     */
    protected function createUserForPhone(string $phoneNumber): ?User
    {
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if ($cleanNumber === '')
        {
            return null;
        }

        $email = 'wa-'.$cleanNumber.'@chat.placeholder';
        if (User::withoutGlobalScopes()->where('email', $email)->exists())
        {
            return User::withoutGlobalScopes()->where('email', $email)->first();
        }

        try
        {
            $user = User::create([
                'name' => 'Usuario '.$cleanNumber,
                'email' => $email,
                'phone' => strlen($cleanNumber) <= 15 ? (int) $cleanNumber : $cleanNumber,
                'password' => bcrypt(Str::random(32)),
            ]);

            if (class_exists(\Spatie\Permission\Models\Role::class))
            {
                $clientRole = \Spatie\Permission\Models\Role::findByName('client');
                if ($clientRole)
                {
                    $user->assignRole($clientRole);
                }
            }

            return $user;
        } catch (\Throwable $e)
        {
            report($e);

            return null;
        }
    }

    /**
     * Create a minimal User from contact data when contact has no user_id.
     */
    protected function createUserForContact(Contact $contact): ?User
    {
        $email = $contact->email && filter_var($contact->email, FILTER_VALIDATE_EMAIL)
            ? $contact->email
            : 'contact-'.$contact->id.'@chat.placeholder';

        if (User::withoutGlobalScopes()->where('email', $email)->exists())
        {
            return User::withoutGlobalScopes()->where('email', $email)->first();
        }

        $cleanPhone = $contact->phone ? preg_replace('/[^0-9]/', '', (string) $contact->phone) : null;
        $name = trim($contact->name.' '.$contact->surname) ?: 'Contacto '.$contact->id;

        try
        {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $cleanPhone ?: null,
                'password' => bcrypt(Str::random(32)),
            ]);

            $contact->update(['user_id' => $user->id]);

            if (class_exists(\Spatie\Permission\Models\Role::class))
            {
                $clientRole = \Spatie\Permission\Models\Role::findByName('client');
                if ($clientRole)
                {
                    $user->assignRole($clientRole);
                }
            }

            return $user;
        } catch (\Throwable $e)
        {
            report($e);

            return null;
        }
    }
}

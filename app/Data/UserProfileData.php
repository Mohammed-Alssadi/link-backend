<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class UserProfileData extends Data
{
    public function __construct(
        public string|int $id,
        public string $name,
        public string $email,
        public ?string $mobile = null,
        public ?string $avatar = null,
        public string $role = 'مدير متجر',
        public bool $is_email_verified = true,
        public ?string $gender = null,
        public ?string $language = null,
        public ?string $birth_date = null,
        public ?string $created_at = null,
        public ?string $uuid = null
    ) {}

    public static function fromSalla(array $sallaUserData): self
    {
        $data = $sallaUserData['data'] ?? $sallaUserData;
        $merchant = $data['merchant'] ?? [];

        return new self(
            id: $data['id'] ?? ($merchant['id'] ?? 1),
            name: (string) ($data['name'] ?? ($merchant['name'] ?? '')),
            email: (string) ($data['email'] ?? ($merchant['username'] ?? '')),
            mobile: $data['mobile'] ?? null,
            avatar: $merchant['avatar'] ?? ($data['avatar'] ?? null),
            role: 'تاجر سلة',
            is_email_verified: true,
            created_at: $data['created_at'] ?? ($merchant['created_at'] ?? null)
        );
    }

    public static function fromZid(array $zidUserData): self
    {
        $user = $zidUserData['user'] ?? ($zidUserData['data']['user'] ?? $zidUserData);

        return new self(
            id: $user['id'] ?? 1,
            name: (string) ($user['name'] ?? ''),
            email: (string) ($user['email'] ?? ''),
            mobile: $user['mobile'] ?? null,
            avatar: $user['avatar'] ?? null,
            role: 'تاجر زد',
            is_email_verified: (bool) ($user['is_email_verified'] ?? true),
            gender: $user['gender'] ?? null,
            language: $user['language_code'] ?? 'ar',
            birth_date: $user['user_profile_data']['birth_date'] ?? null,
            created_at: $user['created_at'] ?? null,
            uuid: $user['uuid'] ?? null
        );
    }
}

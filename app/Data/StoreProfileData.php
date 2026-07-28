<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class StoreLicensesData extends Data
{
    public function __construct(
        public string $taxNumber = '',
        public string $commercialNumber = '',
        public string $freelanceNumber = ''
    ) {}
}

class StoreSocialData extends Data
{
    public function __construct(
        public string $whatsapp = '',
        public string $twitter = '',
        public string $instagram = '',
        public string $snapchat = '',
        public string $telegram = '',
        public string $youtube = '',
        public string $maroof = '',
        public string $facebook = '',
        public string $tiktok = ''
    ) {}
}

class ZidBrandingData extends Data
{
    public function __construct(
        public ?array $theme = null,
        public ?string $logo = null,
        public ?string $icon = null,
        public ?string $cover = null,
        public ?array $colors = null
    ) {}
}

class ZidLocalizationData extends Data
{
    public function __construct(
        public ?array $language = null,
        public array $languages = [],
        public ?array $currency = null,
        public array $currencies = []
    ) {}
}

class ZidBusinessData extends Data
{
    public function __construct(
        public ?string $businessType = null,
        public ?string $corporateName = null,
        public ?string $commercialName = null,
        public ?int $maroofNumber = null,
        public ?int $civilId = null,
        public bool $hasBranches = false,
        public ?int $branchCount = null,
        public ?int $employeeCount = null,
        public ?string $email = null,
        public bool $isMaroofChecked = false,
        public bool $isFreelanceChecked = false,
        public ?string $commercialRegisterCertificate = null,
        public ?string $maroofCertificate = null,
        public ?string $civilIdImage = null,
        public ?string $commercialRegistrationNumber = null
    ) {}
}

class StoreProfileData extends Data
{
    public function __construct(
        public string|int|null $id,
        public string $name,
        public string $domain,
        public ?string $avatar = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $timezone = null,
        public ?string $plan = null,
        public ?string $status = null,
        public string $description = '',
        public string $currency = 'SAR',
        public bool $verified = true,
        public ?StoreLicensesData $licenses = null,
        public ?StoreSocialData $social = null,
        public ?ZidBrandingData $branding = null,
        public ?ZidLocalizationData $localization = null,
        public ?ZidBusinessData $business = null
    ) {}

    /**
     * تحويل الـ JSON الخام لسلة إلى StoreProfileData مباشر
     */
    public static function fromSalla(array $sallaData): self
    {
        $licensesData = $sallaData['licenses'] ?? [];
        $socialData = $sallaData['social'] ?? [];

        return new self(
            id: $sallaData['id'] ?? null,
            name: (string) ($sallaData['name'] ?? ''),
            domain: (string) ($sallaData['domain'] ?? ''),
            avatar: $sallaData['avatar'] ?? null,
            phone: $sallaData['phone'] ?? null,
            email: $sallaData['email'] ?? null,
            timezone: $sallaData['timezone'] ?? null,
            plan: $sallaData['plan'] ?? null,
            status: $sallaData['status'] ?? null,
            description: (string) ($sallaData['description'] ?? ''),
            currency: (string) ($sallaData['currency'] ?? 'SAR'),
            verified: (bool) ($sallaData['verified'] ?? false),
            licenses: new StoreLicensesData(
                taxNumber: (string) ($licensesData['tax_number'] ?? ''),
                commercialNumber: (string) ($licensesData['commercial_number'] ?? ''),
                freelanceNumber: (string) ($licensesData['freelance_number'] ?? '')
            ),
            social: new StoreSocialData(
                whatsapp: (string) ($socialData['whatsapp'] ?? ''),
                twitter: (string) ($socialData['twitter'] ?? ''),
                instagram: (string) ($socialData['instagram'] ?? ''),
                snapchat: (string) ($socialData['snapchat'] ?? ''),
                telegram: (string) ($socialData['telegram'] ?? ''),
                youtube: (string) ($socialData['youtube'] ?? ''),
                maroof: (string) ($socialData['maroof'] ?? ''),
                facebook: (string) ($socialData['facebook'] ?? ''),
                tiktok: (string) ($socialData['tiktok'] ?? '')
            )
        );
    }

    /**
     * تحويل الـ JSON الخام لـ Zid (من الـ 5 طلبات) إلى StoreProfileData مباشر
     */
    public static function fromZid(
        array $storeData,
        ?array $brandingData = null,
        ?array $socialData = null,
        ?array $localizationData = null,
        ?array $businessData = null
    ): self {
        $store = $storeData['store'] ?? $storeData;

        // Branding
        $branding = null;
        if ($brandingData) {
            $rawTheme = $brandingData['theme'] ?? null;
            $rawColors = $brandingData['colors'] ?? null;

            $branding = new ZidBrandingData(
                theme: $rawTheme ? [
                    'id' => $rawTheme['id'] ?? 0,
                    'name' => $rawTheme['name'] ?? '',
                    'mainImage' => $rawTheme['main_image'] ?? null,
                    'images' => $rawTheme['images'] ?? [],
                ] : null,
                logo: $brandingData['logo'] ?? null,
                icon: $brandingData['icon'] ?? null,
                cover: $brandingData['cover'] ?? null,
                colors: $rawColors ? [
                    'btnDefaultBackground' => $rawColors['btn_default_background_color'] ?? null,
                    'btnDefaultText' => $rawColors['btn_default_text_color'] ?? null,
                    'btnDefaultBorder' => $rawColors['btn_default_border_color'] ?? null,
                    'btnHoverBackground' => $rawColors['btn_hover_background_color'] ?? null,
                    'btnPressedBackground' => $rawColors['btn_pressed_background_color'] ?? null,
                    'btnPressedText' => $rawColors['btn_pressed_text_color'] ?? null,
                    'btnPressedBorder' => $rawColors['btn_pressed_border_color'] ?? null,
                ] : null
            );
        }

        // Social
        // \u0645\u0644\u0627\u062d\u0638\u0629: whatsapp \u064a\u0623\u062a\u064a \u0645\u0646 $socialData\u060c \u0648\u0644\u064a\u0633 \u0645\u0646 $store['phone']\n        // \u0648 telegram \u0648youtube \u0648maroof \u0643\u0627\u0646\u062a \u0645\u0641\u0642\u0648\u062f\u0629 \u0633\u0627\u0628\u0642\u0627\u064b\n        $social = new StoreSocialData(\n            whatsapp:  (string) ($socialData['whatsapp']  ?? $store['phone'] ?? ''),\n            twitter:   (string) ($socialData['twitter']   ?? ''),\n            instagram: (string) ($socialData['instagram'] ?? ''),\n            snapchat:  (string) ($socialData['snapchat']  ?? ''),\n            telegram:  (string) ($socialData['telegram']  ?? ''),\n            youtube:   (string) ($socialData['youtube']   ?? ''),\n            maroof:    (string) ($socialData['maroof']    ?? ''),\n            facebook:  (string) ($socialData['facebook']  ?? ''),\n            tiktok:    (string) ($socialData['tiktok']    ?? '')\n        );

        // Localization
        $localization = null;
        if ($localizationData) {
            $lang = $localizationData['language'] ?? null;
            $curr = $localizationData['currency'] ?? null;
            $localization = new ZidLocalizationData(
                language: $lang ? ['name' => $lang['name'] ?? '', 'code' => $lang['code'] ?? '', 'direction' => $lang['direction'] ?? 'rtl'] : null,
                languages: array_map(fn ($l) => ['name' => $l['name'] ?? '', 'code' => $l['code'] ?? '', 'direction' => $l['direction'] ?? 'rtl'], $localizationData['languages'] ?? []),
                currency: $curr ? ['name' => $curr['name'] ?? '', 'code' => $curr['code'] ?? '', 'symbol' => trim($curr['symbol'] ?? ''), 'flag' => $curr['country']['flag'] ?? null, 'countryName' => $curr['country']['name'] ?? null, 'countryCode' => $curr['country']['code'] ?? null] : null,
                currencies: array_map(fn ($c) => ['name' => $c['name'] ?? '', 'code' => $c['code'] ?? '', 'symbol' => trim($c['symbol'] ?? ''), 'flag' => $c['country']['flag'] ?? null, 'countryName' => $c['country']['name'] ?? null, 'countryCode' => $c['country']['code'] ?? null], $localizationData['currencies'] ?? [])
            );
        }

        // Business
        $business = null;
        if ($businessData) {
            $bd = $businessData['store_business_data'] ?? null;
            if ($bd) {
                $business = new ZidBusinessData(
                    businessType: $bd['business_type'] ?? null,
                    corporateName: $bd['business_corporate_name'] ?? null,
                    commercialName: $bd['commercial_name'] ?? null,
                    maroofNumber: isset($bd['maroof_number']) ? (int) $bd['maroof_number'] : null,
                    civilId: isset($bd['civil_id']) ? (int) $bd['civil_id'] : null,
                    hasBranches: (bool) ($bd['has_branches'] ?? false),
                    branchCount: isset($bd['branch_no']) ? (int) $bd['branch_no'] : null,
                    employeeCount: isset($bd['employee_no']) ? (int) $bd['employee_no'] : null,
                    email: $bd['email'] ?? null,
                    isMaroofChecked: (bool) ($bd['is_maroof_checked'] ?? false),
                    isFreelanceChecked: (bool) ($bd['is_freelance_checked'] ?? false),
                    commercialRegisterCertificate: $bd['commercial_register_certificate'] ?? null,
                    maroofCertificate: $bd['maroof_certificate'] ?? null,
                    civilIdImage: $bd['civil_id_image'] ?? null,
                    commercialRegistrationNumber: $businessData['commercial_registration_number'] ?? null
                );
            }
        }

        $licenses = new StoreLicensesData(
            taxNumber: (string) ($store['tax_number'] ?? ''),
            commercialNumber: (string) ($store['commercial_register'] ?? '')
        );

        $avatar = $branding?->icon ?? ($branding?->logo ?? ($store['logo'] ?? null));

        return new self(
            id: $store['id'] ?? null,
            name: (string) ($store['title'] ?? ''),
            domain: (string) ($store['url'] ?? ''),
            avatar: $avatar,
            phone: $store['phone'] ?? null,
            email: $store['email'] ?? null,
            timezone: $store['timezone'] ?? null,
            plan: $store['plan_name'] ?? ($store['plan'] ?? null),
            status: $store['status'] ?? null,
            description: (string) ($store['description'] ?? ''),
            currency: (string) ($localization?->currency['code'] ?? ($store['currency'] ?? 'SAR')),
            verified: true,
            licenses: $licenses,
            social: $social,
            branding: $branding,
            localization: $localization,
            business: $business
        );
    }
}

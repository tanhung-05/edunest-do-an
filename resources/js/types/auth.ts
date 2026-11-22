export type RoleCode =
    | 'HOC_VIEN'
    | 'GIAO_VIEN'
    | 'GIAO_VU'
    | 'QUAN_TRI'
    | 'PHU_HUYNH';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    roles: RoleCode[];
    effective_roles: RoleCode[];
    is_affiliate?: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

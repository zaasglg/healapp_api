<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="HealApp API",
 *     version="1.0.0",
 *     description="API для мобильной экосистемы HealApp"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * ============================================
 * ENUMS
 * ============================================
 * 
 * @OA\Schema(
 *     schema="UserType",
 *     type="string",
 *     description="Тип пользователя (НЕ роль!)",
 *     enum={"organization", "private_caregiver", "client"}
 * )
 * 
 * @OA\Schema(
 *     schema="OrganizationType",
 *     type="string",
 *     description="Тип организации",
 *     enum={"agency", "boarding_house"}
 * )
 * 
 * @OA\Schema(
 *     schema="OrganizationRole",
 *     type="string",
 *     description="Роль сотрудника в организации (Spatie)",
 *     enum={"owner", "admin", "doctor", "caregiver"}
 * )
 * 
 * @OA\Schema(
 *     schema="InvitationType",
 *     type="string",
 *     description="Тип приглашения",
 *     enum={"employee", "client"}
 * )
 * 
 * @OA\Schema(
 *     schema="InvitationStatus",
 *     type="string",
 *     description="Статус приглашения",
 *     enum={"pending", "accepted", "expired", "revoked"}
 * )
 * 
 * ============================================
 * USER
 * ============================================
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="type", ref="#/components/schemas/UserType"),
 *     @OA\Property(property="account_type", type="string", description="Вычисляемый тип аккаунта"),
 *     @OA\Property(property="role", ref="#/components/schemas/OrganizationRole", nullable=true),
 *     @OA\Property(property="organization", ref="#/components/schemas/Organization", nullable=true),
 *     @OA\Property(property="phone_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * ============================================
 * ORGANIZATION
 * ============================================
 * 
 * @OA\Schema(
 *     schema="Organization",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="owner_id", type="integer"),
 *     @OA\Property(property="name", type="string", nullable=true),
 *     @OA\Property(property="type", ref="#/components/schemas/OrganizationType"),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="address", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="employee_count", type="integer"),
 *     @OA\Property(property="patient_count", type="integer"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="Employee",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="role", ref="#/components/schemas/OrganizationRole"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * ============================================
 * INVITATION
 * ============================================
 * 
 * @OA\Schema(
 *     schema="Invitation",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="organization_id", type="integer"),
 *     @OA\Property(property="inviter_id", type="integer"),
 *     @OA\Property(property="invitee_id", type="integer", nullable=true),
 *     @OA\Property(property="token", type="string"),
 *     @OA\Property(property="type", ref="#/components/schemas/InvitationType"),
 *     @OA\Property(property="role", ref="#/components/schemas/OrganizationRole", nullable=true),
 *     @OA\Property(property="patient_id", type="integer", nullable=true),
 *     @OA\Property(property="status", ref="#/components/schemas/InvitationStatus"),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * ============================================
 * PATIENT
 * ============================================
 * 
 * @OA\Schema(
 *     schema="Patient",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="owner_id", type="integer", nullable=true, description="ID клиента-владельца"),
 *     @OA\Property(property="creator_id", type="integer"),
 *     @OA\Property(property="organization_id", type="integer", nullable=true),
 *     @OA\Property(property="first_name", type="string"),
 *     @OA\Property(property="last_name", type="string"),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="birth_date", type="string", format="date"),
 *     @OA\Property(property="gender", type="string", enum={"male", "female"}),
 *     @OA\Property(property="weight", type="number", nullable=true),
 *     @OA\Property(property="height", type="number", nullable=true),
 *     @OA\Property(property="diagnoses", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * ============================================
 * DIARY
 * ============================================
 * 
 * @OA\Schema(
 *     schema="Diary",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="patient_id", type="integer"),
 *     @OA\Property(property="pinned_parameters", type="array", @OA\Items(ref="#/components/schemas/PinnedParameter")),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DiaryEntry",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="diary_id", type="integer"),
 *     @OA\Property(property="author_id", type="integer"),
 *     @OA\Property(property="type", type="string", enum={"care", "physical", "excretion", "symptom"}),
 *     @OA\Property(property="key", type="string"),
 *     @OA\Property(property="value", type="object"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="recorded_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DiaryAccess",
 *     type="object",
 *     @OA\Property(property="diary_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="permission", type="string", enum={"view", "edit", "full"}),
 *     @OA\Property(property="status", type="string", enum={"active", "revoked"})
 * )
 * 
 * @OA\Schema(
 *     schema="PinnedParameter",
 *     type="object",
 *     @OA\Property(property="key", type="string"),
 *     @OA\Property(property="interval_minutes", type="integer"),
 *     @OA\Property(property="last_recorded_at", type="string", format="date-time", nullable=true)
 * )
 */
abstract class Controller
{
    //
}

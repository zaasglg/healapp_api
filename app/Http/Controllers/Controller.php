<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="HealApp API",
 *     version="1.0.0",
 *     description="API для мобильной экосистемы HealApp - организации ухода за пожилыми людьми и людьми с ограниченными возможностями"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * 
 * @OA\Schema(
 *     schema="Diary",
 *     type="object",
 *     description="Дневник подопечного",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="patient_id", type="integer", example=1, description="ID пациента"),
 *     @OA\Property(property="pinned_parameters", type="array", description="Закреплённые показатели с таймерами",
 *         @OA\Items(
 *             @OA\Property(property="key", type="string", example="blood_pressure"),
 *             @OA\Property(property="interval_minutes", type="integer", example=60),
 *             @OA\Property(property="last_recorded_at", type="string", format="date-time", nullable=true)
 *         )
 *     ),
 *     @OA\Property(property="settings", type="object", nullable=true, description="Настройки дневника"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DiaryEntry",
 *     type="object",
 *     description="Запись в дневнике",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="diary_id", type="integer", example=1, description="ID дневника"),
 *     @OA\Property(property="author_id", type="integer", example=1, description="ID автора записи"),
 *     @OA\Property(property="type", type="string", example="physical", enum={"care", "physical", "excretion", "symptom"}, description="Тип записи"),
 *     @OA\Property(property="key", type="string", example="blood_pressure", description="Ключ показателя"),
 *     @OA\Property(property="value", type="object", description="Значение показателя в JSON формате"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Normal reading", description="Заметки"),
 *     @OA\Property(property="recorded_at", type="string", format="date-time", description="Время записи события"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 * 
 * @OA\Schema(
 *     schema="DiaryAccess",
 *     type="object",
 *     description="Доступ к дневнику",
 *     @OA\Property(property="diary_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=2),
 *     @OA\Property(property="permission", type="string", enum={"view", "edit", "full"}, example="edit")
 * )
 * 
 * @OA\Schema(
 *     schema="PinnedParameter",
 *     type="object",
 *     description="Закреплённый показатель с таймером",
 *     @OA\Property(property="key", type="string", example="blood_pressure", description="Ключ показателя"),
 *     @OA\Property(property="interval_minutes", type="integer", example=60, description="Интервал замера в минутах"),
 *     @OA\Property(property="last_recorded_at", type="string", format="date-time", nullable=true, description="Время последней записи")
 * )
 */
abstract class Controller
{
    //
}

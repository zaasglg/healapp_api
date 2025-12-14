<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="HealApp API",
 *     version="1.0.0"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
abstract class Controller
{
    //
}

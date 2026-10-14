<?php

/*
 * Mensajes de validación en español (APP_LOCALE=es). Solo las reglas que usa
 * la API; una regla que no esté aquí sale en inglés (fallback en).
 */
return [
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'alpha' => 'El campo :attribute solo puede contener letras.',
    'alpha_dash' => 'El campo :attribute solo puede contener letras, números, guiones y guiones bajos.',
    'array' => 'El campo :attribute debe ser una lista.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
        'string' => 'El campo :attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'date' => 'El campo :attribute no es una fecha válida.',
    'date_format' => 'El campo :attribute no tiene el formato :format.',
    'distinct' => 'El campo :attribute tiene un valor repetido.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'exists' => 'El :attribute seleccionado no existe.',
    'in' => 'El valor de :attribute no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe tener más de :max caracteres.',
        'array' => 'El campo :attribute no debe tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
        'array' => 'El campo :attribute debe tener al menos :min elementos.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'size' => [
        'string' => 'El campo :attribute debe tener :size caracteres.',
        'array' => 'El campo :attribute debe tener :size elementos.',
    ],
    'string' => 'El campo :attribute debe ser texto.',
    'timezone' => 'El campo :attribute debe ser una zona horaria válida.',
    'unique' => 'Ya existe un registro con ese :attribute.',
    'uuid' => 'El campo :attribute debe ser un UUID válido.',

    'attributes' => [
        'canal_preferido' => 'canal preferido',
        'correo' => 'correo',
        'fecha_fin' => 'fecha de fin',
        'fecha_inicio' => 'fecha de inicio',
        'grado_id' => 'grado',
        'grupo_id' => 'grupo',
        'numero_empleado' => 'número de empleado',
        'padre_tutor_id' => 'padre o tutor',
        'password' => 'contraseña',
        'periodo_id' => 'periodo',
        'plantel_id' => 'plantel',
        'rol_id' => 'rol',
        'telefono' => 'teléfono',
        'tipo_evaluacion' => 'tipo de evaluación',
        'username' => 'usuario',
    ],

    'values' => [
        'canal_preferido' => ['sms' => 'SMS', 'email' => 'correo'],
    ],
];

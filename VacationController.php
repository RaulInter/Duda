<?php

public function store(VacationRequest $request)
{
    // Obtener el usuario autenticado
    $user = Auth::user();

    // Validar los datos del formulario
    $validatedData = $request->validated();

    // Obtener la fecha actual y las fechas de inicio y final de las vacaciones
    $currentDate = Carbon::now()->startOfDay();
    $daysDifference = $this->getDuration($validatedData['initialDate'], $request['finalDate']);
    $initialDate = Carbon::createFromFormat('Y-m-d', $validatedData['initialDate'])->startOfDay();
    $finalDate = Carbon::createFromFormat('Y-m-d', $request['finalDate'])->endOfDay();
    $extraInitialDate = $request->filled('extraInitialDate') ? Carbon::createFromFormat('Y-m-d', $validatedData['extraInitialDate'])->startOfDay() : null;
    $extraFinalDate = $request->filled('extraFinalDate') ? Carbon::createFromFormat('Y-m-d', $validatedData['extraFinalDate'])->endOfDay() : null;
    // Corrección en el cálculo de días adicionales
    $extraDaysDifference = $extraInitialDate && $extraFinalDate ? $extraFinalDate->diffInDays($extraInitialDate) + 1 : 0;

    // Verificar la fecha inicial no es anterior al día actual
    if ($initialDate->lessThan($currentDate)) {
        return response()->json(['error' => 'La fecha inicial NO debe ser anterior al día actual.'], 422);
    }

    // Verificar la fecha final no es anterior al día actual
    if ($finalDate->lessThan($currentDate)) {
        return response()->json(['error' => 'La fecha final NO debe ser anterior al día actual.'], 422);
    }

    // Verificar que se soliciten las vacaciones con al menos 3 días de anticipación
    $threeDaysBefore = $currentDate->copy()->subDays(3);
    if ($initialDate->lessThan($threeDaysBefore)) {
        return response()->json([
            'error' => 'No se pueden solicitar vacaciones para la fecha seleccionada. Las vacaciones deben solicitarse con al menos 3 días de anticipación.',
        ], 422);
    }

    // Comprobar si ya existe una solicitud de vacaciones con detalles idénticos y estado "Por Aprobar" o "Aprobadas"
    $existingVacation = Vacacion::where('user_id', $user->id)
        ->where('initialDate', $request->input('initialDate'))
        ->where('finalDate', $request->input('finalDate'))
        ->where('boss', $request->input('boss'))
        ->where('motive', $request->input('motive'))
        ->where('description', $request->input('description'))
        ->where('color', $request->input('color'))
        ->whereIn('status', ['Por Aprobar', 'Aprobadas'])
        ->first();

    if ($existingVacation) {
        return response()->json(['error' => 'Ya existe una solicitud de vacaciones con idénticos detalles y estado "Por Aprobar" o "Aprobadas".'], 422);
    }

    // Comprobar si ya existe una solicitud de vacaciones con la misma fecha inicial y final para este usuario
    $dateConflict = Vacacion::where('user_id', $user->id)
        ->where('initialDate', $request->input('initialDate'))
        ->where('finalDate', $request->input('finalDate'))
        ->first();

    if ($dateConflict) {
        return response()->json(['error' => 'Ya existe una solicitud de vacaciones con la misma fecha inicial y fecha final para este usuario. Por favor elija otras fechas.'], 422);
    }

    // Comprobar si existe un período vacacional previo con las mismas fechas y estado "Rechazadas"
    $dateConflictRejected = Vacacion::where('user_id', $user->id)
        ->where('initialDate', $request->input('initialDate'))
        ->where('finalDate', $request->input('finalDate'))
        ->where('status', 'Rechazadas')
        ->first();

    if ($dateConflictRejected) {
        return response()->json(['error' => 'Debido a un período vacacional previamente registrado con las mismas fechas y que el estado de ese periodo de vacaciones está marcado como "Rechazadas", no se puede registrar este "nuevo" período vacacional, hasta que no se elimine el anterior registro. Por favor elija fechas diferentes.'], 422);
    }

    // Actualizar o insertar una notificación
    $section = 'Vacaciones';
    NotificationService::updateOrInsertNotification($user, $section);

    // Obtener los valores validados de la solicitud
    $boss = $validatedData['boss'];
    $initialDate = $validatedData['initialDate'];
    $finalDate = $validatedData['finalDate'];
    $extraInitialDate = $validatedData['extraInitialDate'];
    $extraFinalDate = $validatedData['extraFinalDate'];
    $motive = $validatedData['motive'];
    $description = $validatedData['description'];
    $color = $validatedData['color'];
    $status = 'Por Aprobar';

    // Obtener todas las solicitudes aprobadas y pendientes
    $approvedAndPendingVacations = Vacacion::whereIn('status', ['Por Aprobar', 'Aprobadas'])
        ->where('user_id', $user->id)
        ->get();

    // Calcular la duración de las nuevas vacaciones solicitadas
    $requestedDuration = 0; // Inicializamos en 0

    // Calcular los días restantes de vacaciones disponibles
    $remainingDays = $user->remainingDays;

    foreach ($approvedAndPendingVacations as $vacation) {
        $duration = $this->getDuration($vacation->initialDate, $vacation->finalDate);
        $remainingDays -= $duration;
    }

    // Obtener la fecha inicial y final de las vacaciones
    $initialDate = Carbon::createFromFormat('Y-m-d', $validatedData['initialDate'])->startOfDay();
    $finalDate = Carbon::createFromFormat('Y-m-d', $request['finalDate'])->endOfDay();

    // Calcular la duración de las vacaciones
    $daysDifference = $finalDate->diffInDays($initialDate) + 1; // Sumamos 1 para incluir ambos extremos

    // Restar días adicionales a daysDifference si existen
    if ($extraInitialDate && $extraFinalDate) {
        // Convertir las cadenas de fecha en objetos Carbon
        $extraInitialDate = Carbon::createFromFormat('Y-m-d', $extraInitialDate)->startOfDay();
        $extraFinalDate = Carbon::createFromFormat('Y-m-d', $extraFinalDate)->endOfDay();

        // Calcular la diferencia de días adicionales
        $extraDaysDifference = $extraFinalDate->diffInDays($extraInitialDate) + 1;

        // Restar los días adicionales de la duración total
        $daysDifference -= $extraDaysDifference;
    }

    // Se actualiza remainingDays
    $remainingDays -= $daysDifference;

    // Verificar si hay suficientes días de vacaciones disponibles
    if ($remainingDays < 0) {
        return response()->json(['error' => 'No puedes elegir más días de vacaciones de los que te quedan disponibles (' . $remainingDays . ' días restantes).'], 422);
    }

    // Formatear las fechas para mostrar solo la parte de la fecha
    $initialDateFormatted = $initialDate->toDateString();
    $finalDateFormatted = $finalDate->toDateString();

    // Crear una nueva solicitud de vacaciones
    $vacation = new Vacacion();
    $vacation->employee = $user->name;
    $vacation->boss = $boss;
    $vacation->initialDate = $initialDate;
    $vacation->finalDate = $finalDate;
    $vacation->extraInitialDate = $extraInitialDate;
    $vacation->extraFinalDate = $extraFinalDate;
    $vacation->motive = $motive;
    $vacation->description = $description;
    $vacation->color = $color;
    $vacation->remainingDays = $remainingDays - $requestedDuration;
    $vacation->status = $status;
    $vacation->user_id = $user->id;
    $vacation->daysDifference = $daysDifference;
    $user->remainingDays -= $requestedDuration;

    //Se envía los correos correpondientes de el registro de
    //$destinations = ['correo1@gmail.com.com', 'correo2@gmail.com', 'correo@gmail.com'];
    //$mail = new ContactanosMailable($vacation);
    //Mail::to($destinations)->send($mail);

    // Guardar la nueva solicitud de vacaciones en la base de datos
    try {
        $vacation->save();
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al guardar la solicitud de vacaciones: ' . $e->getMessage()], 500);
    }

    // Devolver una respuesta JSON con un mensaje de éxito y los días restantes de vacaciones
    return response()->json(['message' => 'Vacaciones registradas exitosamente.', 'remainingDays' => $vacation->remainingDays]);
}
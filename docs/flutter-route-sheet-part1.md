# Flutter Implementation - Route Sheet (Маршрутный лист)

## Структура файлов

```
lib/
├── data/
│   ├── models/
│   │   ├── task.dart
│   │   ├── task_template.dart
│   │   └── route_sheet_response.dart
│   └── repositories/
│       └── route_sheet_repository.dart
├── domain/
│   └── bloc/
│       └── route_sheet/
│           ├── route_sheet_bloc.dart
│           ├── route_sheet_event.dart
│           └── route_sheet_state.dart
└── presentation/
    └── pages/
        └── route_sheet/
            ├── route_sheet_page.dart
            ├── widgets/
            │   ├── task_card.dart
            │   ├── time_slot_section.dart
            │   ├── task_detail_dialog.dart
            │   ├── reschedule_dialog.dart
            │   └── complete_task_dialog.dart
            └── create_task/
                ├── create_task_page.dart
                └── create_template_page.dart
```

---

## 1. Models

### task.dart

```dart
import 'package:freezed_annotation/freezed_annotation.dart';

part 'task.freezed.dart';
part 'task.g.dart';

@freezed
class Task with _$Task {
  const Task._();

  const factory Task({
    required int id,
    @JsonKey(name: 'patient_id') required int patientId,
    @JsonKey(name: 'template_id') int? templateId,
    @JsonKey(name: 'assigned_to') int? assignedToId,
    required String title,
    @JsonKey(name: 'start_at') required DateTime startAt,
    @JsonKey(name: 'end_at') required DateTime endAt,
    @JsonKey(name: 'original_start_at') DateTime? originalStartAt,
    @JsonKey(name: 'original_end_at') DateTime? originalEndAt,
    @Default('pending') String status,
    @Default(0) int priority,
    @JsonKey(name: 'completed_at') DateTime? completedAt,
    @JsonKey(name: 'completed_by') int? completedById,
    String? comment,
    List<String>? photos,
    @JsonKey(name: 'reschedule_reason') String? rescheduleReason,
    @JsonKey(name: 'rescheduled_by') int? rescheduledById,
    @JsonKey(name: 'rescheduled_at') DateTime? rescheduledAt,
    @JsonKey(name: 'related_diary_key') String? relatedDiaryKey,
    @JsonKey(name: 'is_rescheduled') @Default(false) bool isRescheduled,
    @JsonKey(name: 'is_overdue') @Default(false) bool isOverdue,
    PatientInfo? patient,
    @JsonKey(name: 'assigned_to') UserInfo? assignedTo,
  }) = _Task;

  factory Task.fromJson(Map<String, dynamic> json) => _$TaskFromJson(json);

  // Helper getters
  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'completed';
  bool get isMissed => status == 'missed';
  bool get isCancelled => status == 'cancelled';

  String get timeRange {
    final startTime = '${startAt.hour.toString().padLeft(2, '0')}:${startAt.minute.toString().padLeft(2, '0')}';
    final endTime = '${endAt.hour.toString().padLeft(2, '0')}:${endAt.minute.toString().padLeft(2, '0')}';
    return '$startTime - $endTime';
  }

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'Ожидает';
      case 'completed':
        return 'Выполнено';
      case 'missed':
        return 'Пропущено';
      case 'cancelled':
        return 'Отменено';
      default:
        return status;
    }
  }
}

@freezed
class PatientInfo with _$PatientInfo {
  const factory PatientInfo({
    required int id,
    @JsonKey(name: 'first_name') String? firstName,
    @JsonKey(name: 'last_name') String? lastName,
  }) = _PatientInfo;

  factory PatientInfo.fromJson(Map<String, dynamic> json) =>
      _$PatientInfoFromJson(json);
}

@freezed
class UserInfo with _$UserInfo {
  const factory UserInfo({
    required int id,
    String? name,
    @JsonKey(name: 'first_name') String? firstName,
    @JsonKey(name: 'last_name') String? lastName,
  }) = _UserInfo;

  factory UserInfo.fromJson(Map<String, dynamic> json) =>
      _$UserInfoFromJson(json);
}
```

### task_template.dart

```dart
import 'package:freezed_annotation/freezed_annotation.dart';

part 'task_template.freezed.dart';
part 'task_template.g.dart';

@freezed
class TaskTemplate with _$TaskTemplate {
  const factory TaskTemplate({
    required int id,
    @JsonKey(name: 'patient_id') required int patientId,
    @JsonKey(name: 'creator_id') required int creatorId,
    @JsonKey(name: 'assigned_to') int? assignedToId,
    required String title,
    @JsonKey(name: 'days_of_week') List<int>? daysOfWeek,
    @JsonKey(name: 'time_ranges') required List<TimeRange> timeRanges,
    @JsonKey(name: 'start_date') required DateTime startDate,
    @JsonKey(name: 'end_date') DateTime? endDate,
    @JsonKey(name: 'is_active') @Default(true) bool isActive,
    @JsonKey(name: 'related_diary_key') String? relatedDiaryKey,
  }) = _TaskTemplate;

  factory TaskTemplate.fromJson(Map<String, dynamic> json) =>
      _$TaskTemplateFromJson(json);
}

@freezed
class TimeRange with _$TimeRange {
  const factory TimeRange({
    required String start,
    required String end,
    @JsonKey(name: 'assigned_to') int? assignedTo,
    int? priority,
  }) = _TimeRange;

  factory TimeRange.fromJson(Map<String, dynamic> json) =>
      _$TimeRangeFromJson(json);
}
```

### route_sheet_response.dart

```dart
import 'package:freezed_annotation/freezed_annotation.dart';
import 'task.dart';

part 'route_sheet_response.freezed.dart';
part 'route_sheet_response.g.dart';

@freezed
class RouteSheetResponse with _$RouteSheetResponse {
  const factory RouteSheetResponse({
    required String date,
    @JsonKey(name: 'from_date') String? fromDate,
    @JsonKey(name: 'to_date') String? toDate,
    required List<Task> tasks,
    required TaskSummary summary,
  }) = _RouteSheetResponse;

  factory RouteSheetResponse.fromJson(Map<String, dynamic> json) =>
      _$RouteSheetResponseFromJson(json);
}

@freezed
class TaskSummary with _$TaskSummary {
  const factory TaskSummary({
    required int total,
    required int pending,
    required int completed,
    required int missed,
    @Default(0) int overdue,
  }) = _TaskSummary;

  factory TaskSummary.fromJson(Map<String, dynamic> json) =>
      _$TaskSummaryFromJson(json);
}

@freezed
class MyTasksResponse with _$MyTasksResponse {
  const factory MyTasksResponse({
    required String date,
    required List<Task> tasks,
    @JsonKey(name: 'time_slots') Map<String, List<Task>>? timeSlots,
    required TaskSummary summary,
  }) = _MyTasksResponse;

  factory MyTasksResponse.fromJson(Map<String, dynamic> json) =>
      _$MyTasksResponseFromJson(json);
}

@freezed
class AvailableEmployee with _$AvailableEmployee {
  const factory AvailableEmployee({
    required int id,
    required String name,
    String? role,
    @JsonKey(name: 'is_available') required bool isAvailable,
    @JsonKey(name: 'conflicting_tasks_count') @Default(0) int conflictingTasksCount,
  }) = _AvailableEmployee;

  factory AvailableEmployee.fromJson(Map<String, dynamic> json) =>
      _$AvailableEmployeeFromJson(json);
}
```

---

## 2. Repository

### route_sheet_repository.dart

```dart
import 'dart:io';
import 'package:dio/dio.dart';
import '../models/task.dart';
import '../models/task_template.dart';
import '../models/route_sheet_response.dart';

class RouteSheetRepository {
  final Dio _dio;

  RouteSheetRepository(this._dio);

  // ==================== Route Sheet ====================

  /// Get route sheet (tasks) for a specific date
  Future<RouteSheetResponse> getRouteSheet({
    int? patientId,
    DateTime? date,
    DateTime? fromDate,
    DateTime? toDate,
    String? status,
  }) async {
    final queryParams = <String, dynamic>{};
    
    if (patientId != null) queryParams['patient_id'] = patientId;
    if (date != null) queryParams['date'] = _formatDate(date);
    if (fromDate != null) queryParams['from_date'] = _formatDate(fromDate);
    if (toDate != null) queryParams['to_date'] = _formatDate(toDate);
    if (status != null) queryParams['status'] = status;

    final response = await _dio.get(
      '/route-sheet',
      queryParameters: queryParams,
    );

    return RouteSheetResponse.fromJson(response.data);
  }

  /// Get current user's tasks (for caregivers)
  Future<MyTasksResponse> getMyTasks({DateTime? date}) async {
    final queryParams = <String, dynamic>{};
    if (date != null) queryParams['date'] = _formatDate(date);

    final response = await _dio.get(
      '/route-sheet/my-tasks',
      queryParameters: queryParams,
    );

    return MyTasksResponse.fromJson(response.data);
  }

  /// Get available employees for task assignment
  Future<List<AvailableEmployee>> getAvailableEmployees({
    required int patientId,
    required DateTime startAt,
    required DateTime endAt,
  }) async {
    final response = await _dio.get(
      '/route-sheet/available-employees',
      queryParameters: {
        'patient_id': patientId,
        'start_at': _formatDateTime(startAt),
        'end_at': _formatDateTime(endAt),
      },
    );

    final employees = response.data['employees'] as List;
    return employees
        .map((e) => AvailableEmployee.fromJson(e))
        .toList();
  }

  /// Get single task
  Future<Task> getTask(int taskId) async {
    final response = await _dio.get('/route-sheet/$taskId');
    return Task.fromJson(response.data);
  }

  /// Create a single task (without template)
  Future<Task> createTask({
    required int patientId,
    required String title,
    required DateTime startAt,
    required DateTime endAt,
    int? assignedTo,
    int? priority,
    String? relatedDiaryKey,
  }) async {
    final response = await _dio.post(
      '/route-sheet',
      data: {
        'patient_id': patientId,
        'title': title,
        'start_at': _formatDateTime(startAt),
        'end_at': _formatDateTime(endAt),
        if (assignedTo != null) 'assigned_to': assignedTo,
        if (priority != null) 'priority': priority,
        if (relatedDiaryKey != null) 'related_diary_key': relatedDiaryKey,
      },
    );

    return Task.fromJson(response.data);
  }

  /// Update a task
  Future<Task> updateTask({
    required int taskId,
    String? title,
    DateTime? startAt,
    DateTime? endAt,
    int? assignedTo,
    int? priority,
  }) async {
    final data = <String, dynamic>{};
    if (title != null) data['title'] = title;
    if (startAt != null) data['start_at'] = _formatDateTime(startAt);
    if (endAt != null) data['end_at'] = _formatDateTime(endAt);
    if (assignedTo != null) data['assigned_to'] = assignedTo;
    if (priority != null) data['priority'] = priority;

    final response = await _dio.put('/route-sheet/$taskId', data: data);
    return Task.fromJson(response.data);
  }

  /// Reschedule a task
  Future<Task> rescheduleTask({
    required int taskId,
    required DateTime startAt,
    required DateTime endAt,
    required String reason,
  }) async {
    final response = await _dio.post(
      '/route-sheet/$taskId/reschedule',
      data: {
        'start_at': _formatDateTime(startAt),
        'end_at': _formatDateTime(endAt),
        'reason': reason,
      },
    );

    return Task.fromJson(response.data);
  }

  /// Complete a task
  Future<Task> completeTask({
    required int taskId,
    String? comment,
    List<File>? photos,
    Map<String, dynamic>? value,
    DateTime? completedAt,
  }) async {
    FormData formData = FormData();

    if (comment != null) {
      formData.fields.add(MapEntry('comment', comment));
    }

    if (completedAt != null) {
      formData.fields.add(MapEntry('completed_at', _formatDateTime(completedAt)));
    }

    if (value != null) {
      // Send value as JSON string or individual fields
      value.forEach((key, val) {
        formData.fields.add(MapEntry('value[$key]', val.toString()));
      });
    }

    if (photos != null && photos.isNotEmpty) {
      for (var photo in photos) {
        formData.files.add(MapEntry(
          'photos[]',
          await MultipartFile.fromFile(photo.path),
        ));
      }
    }

    final response = await _dio.post(
      '/route-sheet/$taskId/complete',
      data: formData,
    );

    return Task.fromJson(response.data);
  }

  /// Mark task as missed
  Future<Task> missTask({
    required int taskId,
    required String reason,
  }) async {
    final response = await _dio.post(
      '/route-sheet/$taskId/miss',
      data: {'reason': reason},
    );

    return Task.fromJson(response.data);
  }

  /// Delete a task
  Future<void> deleteTask(int taskId) async {
    await _dio.delete('/route-sheet/$taskId');
  }

  // ==================== Task Templates ====================

  /// Get task templates for a patient
  Future<List<TaskTemplate>> getTaskTemplates(int patientId) async {
    final response = await _dio.get(
      '/task-templates',
      queryParameters: {'patient_id': patientId},
    );

    final templates = response.data as List;
    return templates.map((t) => TaskTemplate.fromJson(t)).toList();
  }

  /// Get single template
  Future<TaskTemplate> getTaskTemplate(int templateId) async {
    final response = await _dio.get('/task-templates/$templateId');
    return TaskTemplate.fromJson(response.data);
  }

  /// Create a task template
  Future<TaskTemplate> createTaskTemplate({
    required int patientId,
    required String title,
    int? assignedTo,
    List<int>? daysOfWeek,
    required List<TimeRange> timeRanges,
    required DateTime startDate,
    DateTime? endDate,
    bool isActive = true,
    String? relatedDiaryKey,
  }) async {
    final response = await _dio.post(
      '/task-templates',
      data: {
        'patient_id': patientId,
        'title': title,
        if (assignedTo != null) 'assigned_to': assignedTo,
        if (daysOfWeek != null) 'days_of_week': daysOfWeek,
        'time_ranges': timeRanges.map((tr) => tr.toJson()).toList(),
        'start_date': _formatDate(startDate),
        if (endDate != null) 'end_date': _formatDate(endDate),
        'is_active': isActive,
        if (relatedDiaryKey != null) 'related_diary_key': relatedDiaryKey,
      },
    );

    return TaskTemplate.fromJson(response.data);
  }

  /// Update a task template
  Future<TaskTemplate> updateTaskTemplate({
    required int templateId,
    String? title,
    int? assignedTo,
    List<int>? daysOfWeek,
    List<TimeRange>? timeRanges,
    DateTime? startDate,
    DateTime? endDate,
    bool? isActive,
    String? relatedDiaryKey,
  }) async {
    final data = <String, dynamic>{};
    if (title != null) data['title'] = title;
    if (assignedTo != null) data['assigned_to'] = assignedTo;
    if (daysOfWeek != null) data['days_of_week'] = daysOfWeek;
    if (timeRanges != null) {
      data['time_ranges'] = timeRanges.map((tr) => tr.toJson()).toList();
    }
    if (startDate != null) data['start_date'] = _formatDate(startDate);
    if (endDate != null) data['end_date'] = _formatDate(endDate);
    if (isActive != null) data['is_active'] = isActive;
    if (relatedDiaryKey != null) data['related_diary_key'] = relatedDiaryKey;

    final response = await _dio.put('/task-templates/$templateId', data: data);
    return TaskTemplate.fromJson(response.data);
  }

  /// Toggle template active status
  Future<bool> toggleTaskTemplate(int templateId) async {
    final response = await _dio.patch('/task-templates/$templateId/toggle');
    return response.data['is_active'] as bool;
  }

  /// Delete a task template
  Future<void> deleteTaskTemplate(int templateId) async {
    await _dio.delete('/task-templates/$templateId');
  }

  // ==================== Helpers ====================

  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  String _formatDateTime(DateTime dateTime) {
    return '${_formatDate(dateTime)} ${dateTime.hour.toString().padLeft(2, '0')}:${dateTime.minute.toString().padLeft(2, '0')}:00';
  }
}
```

---

## 3. BLoC

### route_sheet_event.dart

```dart
import 'dart:io';
import 'package:freezed_annotation/freezed_annotation.dart';
import '../../data/models/task_template.dart';

part 'route_sheet_event.freezed.dart';

@freezed
class RouteSheetEvent with _$RouteSheetEvent {
  /// Load route sheet for a date
  const factory RouteSheetEvent.loadTasks({
    int? patientId,
    required DateTime date,
  }) = LoadTasks;

  /// Load current user's tasks (for caregivers)
  const factory RouteSheetEvent.loadMyTasks({
    required DateTime date,
  }) = LoadMyTasks;

  /// Refresh tasks
  const factory RouteSheetEvent.refresh() = RefreshTasks;

  /// Change selected date
  const factory RouteSheetEvent.changeDate(DateTime date) = ChangeDate;

  /// Complete a task
  const factory RouteSheetEvent.completeTask({
    required int taskId,
    String? comment,
    List<File>? photos,
    Map<String, dynamic>? value,
  }) = CompleteTask;

  /// Mark task as missed
  const factory RouteSheetEvent.missTask({
    required int taskId,
    required String reason,
  }) = MissTask;

  /// Reschedule a task
  const factory RouteSheetEvent.rescheduleTask({
    required int taskId,
    required DateTime startAt,
    required DateTime endAt,
    required String reason,
  }) = RescheduleTask;

  /// Create a single task
  const factory RouteSheetEvent.createTask({
    required int patientId,
    required String title,
    required DateTime startAt,
    required DateTime endAt,
    int? assignedTo,
    int? priority,
    String? relatedDiaryKey,
  }) = CreateTask;

  /// Delete a task
  const factory RouteSheetEvent.deleteTask(int taskId) = DeleteTask;

  /// Load task templates
  const factory RouteSheetEvent.loadTemplates(int patientId) = LoadTemplates;

  /// Create a task template
  const factory RouteSheetEvent.createTemplate({
    required int patientId,
    required String title,
    int? assignedTo,
    List<int>? daysOfWeek,
    required List<TimeRange> timeRanges,
    required DateTime startDate,
    DateTime? endDate,
    String? relatedDiaryKey,
  }) = CreateTemplate;

  /// Toggle template
  const factory RouteSheetEvent.toggleTemplate(int templateId) = ToggleTemplate;

  /// Delete template
  const factory RouteSheetEvent.deleteTemplate(int templateId) = DeleteTemplate;
}
```

### route_sheet_state.dart

```dart
import 'package:freezed_annotation/freezed_annotation.dart';
import '../../data/models/task.dart';
import '../../data/models/task_template.dart';
import '../../data/models/route_sheet_response.dart';

part 'route_sheet_state.freezed.dart';

@freezed
class RouteSheetState with _$RouteSheetState {
  const factory RouteSheetState({
    @Default(false) bool isLoading,
    @Default(false) bool isSubmitting,
    required DateTime selectedDate,
    @Default([]) List<Task> tasks,
    TaskSummary? summary,
    Map<String, List<Task>>? timeSlots,
    @Default([]) List<TaskTemplate> templates,
    String? errorMessage,
    String? successMessage,
  }) = _RouteSheetState;

  factory RouteSheetState.initial() => RouteSheetState(
        selectedDate: DateTime.now(),
      );
}
```

### route_sheet_bloc.dart

```dart
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../data/repositories/route_sheet_repository.dart';
import 'route_sheet_event.dart';
import 'route_sheet_state.dart';

class RouteSheetBloc extends Bloc<RouteSheetEvent, RouteSheetState> {
  final RouteSheetRepository _repository;
  int? _currentPatientId;

  RouteSheetBloc(this._repository) : super(RouteSheetState.initial()) {
    on<LoadTasks>(_onLoadTasks);
    on<LoadMyTasks>(_onLoadMyTasks);
    on<RefreshTasks>(_onRefresh);
    on<ChangeDate>(_onChangeDate);
    on<CompleteTask>(_onCompleteTask);
    on<MissTask>(_onMissTask);
    on<RescheduleTask>(_onRescheduleTask);
    on<CreateTask>(_onCreateTask);
    on<DeleteTask>(_onDeleteTask);
    on<LoadTemplates>(_onLoadTemplates);
    on<CreateTemplate>(_onCreateTemplate);
    on<ToggleTemplate>(_onToggleTemplate);
    on<DeleteTemplate>(_onDeleteTemplate);
  }

  Future<void> _onLoadTasks(
    LoadTasks event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isLoading: true, errorMessage: null));
    _currentPatientId = event.patientId;

    try {
      final response = await _repository.getRouteSheet(
        patientId: event.patientId,
        date: event.date,
      );

      emit(state.copyWith(
        isLoading: false,
        selectedDate: event.date,
        tasks: response.tasks,
        summary: response.summary,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoading: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onLoadMyTasks(
    LoadMyTasks event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isLoading: true, errorMessage: null));

    try {
      final response = await _repository.getMyTasks(date: event.date);

      emit(state.copyWith(
        isLoading: false,
        selectedDate: event.date,
        tasks: response.tasks,
        summary: response.summary,
        timeSlots: response.timeSlots,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoading: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onRefresh(
    RefreshTasks event,
    Emitter<RouteSheetState> emit,
  ) async {
    if (_currentPatientId != null) {
      add(LoadTasks(patientId: _currentPatientId, date: state.selectedDate));
    } else {
      add(LoadMyTasks(date: state.selectedDate));
    }
  }

  Future<void> _onChangeDate(
    ChangeDate event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(selectedDate: event.date));
    add(RefreshTasks());
  }

  Future<void> _onCompleteTask(
    CompleteTask event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.completeTask(
        taskId: event.taskId,
        comment: event.comment,
        photos: event.photos,
        value: event.value,
      );

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Задача выполнена',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onMissTask(
    MissTask event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.missTask(
        taskId: event.taskId,
        reason: event.reason,
      );

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Задача отмечена как невыполненная',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onRescheduleTask(
    RescheduleTask event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.rescheduleTask(
        taskId: event.taskId,
        startAt: event.startAt,
        endAt: event.endAt,
        reason: event.reason,
      );

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Задача перенесена',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onCreateTask(
    CreateTask event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.createTask(
        patientId: event.patientId,
        title: event.title,
        startAt: event.startAt,
        endAt: event.endAt,
        assignedTo: event.assignedTo,
        priority: event.priority,
        relatedDiaryKey: event.relatedDiaryKey,
      );

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Задача создана',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onDeleteTask(
    DeleteTask event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.deleteTask(event.taskId);

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Задача удалена',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onLoadTemplates(
    LoadTemplates event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isLoading: true, errorMessage: null));

    try {
      final templates = await _repository.getTaskTemplates(event.patientId);

      emit(state.copyWith(
        isLoading: false,
        templates: templates,
      ));
    } catch (e) {
      emit(state.copyWith(
        isLoading: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onCreateTemplate(
    CreateTemplate event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.createTaskTemplate(
        patientId: event.patientId,
        title: event.title,
        assignedTo: event.assignedTo,
        daysOfWeek: event.daysOfWeek,
        timeRanges: event.timeRanges,
        startDate: event.startDate,
        endDate: event.endDate,
        relatedDiaryKey: event.relatedDiaryKey,
      );

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Шаблон создан',
      ));

      add(LoadTemplates(event.patientId));
      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }

  Future<void> _onToggleTemplate(
    ToggleTemplate event,
    Emitter<RouteSheetState> emit,
  ) async {
    try {
      await _repository.toggleTaskTemplate(event.templateId);
      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(errorMessage: e.toString()));
    }
  }

  Future<void> _onDeleteTemplate(
    DeleteTemplate event,
    Emitter<RouteSheetState> emit,
  ) async {
    emit(state.copyWith(isSubmitting: true, errorMessage: null));

    try {
      await _repository.deleteTaskTemplate(event.templateId);

      emit(state.copyWith(
        isSubmitting: false,
        successMessage: 'Шаблон удалён',
      ));

      add(RefreshTasks());
    } catch (e) {
      emit(state.copyWith(
        isSubmitting: false,
        errorMessage: e.toString(),
      ));
    }
  }
}
```

---

## 4. UI Components

### task_card.dart

```dart
import 'package:flutter/material.dart';
import '../../data/models/task.dart';

class TaskCard extends StatelessWidget {
  final Task task;
  final VoidCallback? onTap;
  final VoidCallback? onComplete;
  final VoidCallback? onReschedule;

  const TaskCard({
    super.key,
    required this.task,
    this.onTap,
    this.onComplete,
    this.onReschedule,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: _getStatusColor().withOpacity(0.3),
          width: 1,
        ),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  _buildStatusIndicator(),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          task.title,
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            fontSize: 16,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.access_time,
                              size: 14,
                              color: Colors.grey[600],
                            ),
                            const SizedBox(width: 4),
                            Text(
                              task.timeRange,
                              style: TextStyle(
                                color: Colors.grey[600],
                                fontSize: 13,
                              ),
                            ),
                            if (task.isRescheduled) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 6,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.orange.withOpacity(0.1),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: const Text(
                                  'Перенесено',
                                  style: TextStyle(
                                    color: Colors.orange,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w500,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ],
                    ),
                  ),
                  if (task.isPending) ...[
                    IconButton(
                      icon: const Icon(Icons.check_circle_outline),
                      color: Colors.green,
                      onPressed: onComplete,
                    ),
                  ],
                ],
              ),
              if (task.patient != null) ...[
                const SizedBox(height: 8),
                Row(
                  children: [
                    Icon(Icons.person_outline, size: 14, color: Colors.grey[600]),
                    const SizedBox(width: 4),
                    Text(
                      '${task.patient!.firstName ?? ''} ${task.patient!.lastName ?? ''}',
                      style: TextStyle(
                        color: Colors.grey[600],
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ],
              if (task.isOverdue) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.warning, size: 14, color: Colors.red),
                      SizedBox(width: 4),
                      Text(
                        'Просрочено',
                        style: TextStyle(
                          color: Colors.red,
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusIndicator() {
    return Container(
      width: 4,
      height: 40,
      decoration: BoxDecoration(
        color: _getStatusColor(),
        borderRadius: BorderRadius.circular(2),
      ),
    );
  }

  Color _getStatusColor() {
    if (task.isOverdue) return Colors.red;
    
    switch (task.status) {
      case 'pending':
        return Colors.blue;
      case 'completed':
        return Colors.green;
      case 'missed':
        return Colors.red;
      case 'cancelled':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }
}
```

### time_slot_section.dart

```dart
import 'package:flutter/material.dart';
import '../../data/models/task.dart';
import 'task_card.dart';

class TimeSlotSection extends StatelessWidget {
  final String time;
  final List<Task> tasks;
  final Function(Task) onTaskTap;
  final Function(Task)? onCompleteTask;

  const TimeSlotSection({
    super.key,
    required this.time,
    required this.tasks,
    required this.onTaskTap,
    this.onCompleteTask,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Row(
            children: [
              Container(
                width: 60,
                child: Text(
                  time,
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Colors.grey[700],
                    fontSize: 14,
                  ),
                ),
              ),
              Expanded(
                child: Divider(color: Colors.grey[300]),
              ),
            ],
          ),
        ),
        if (tasks.isEmpty)
          Padding(
            padding: const EdgeInsets.only(left: 76, bottom: 8),
            child: Text(
              'Нет задач',
              style: TextStyle(
                color: Colors.grey[400],
                fontSize: 13,
              ),
            ),
          )
        else
          ...tasks.map((task) => TaskCard(
                task: task,
                onTap: () => onTaskTap(task),
                onComplete: task.isPending
                    ? () => onCompleteTask?.call(task)
                    : null,
              )),
      ],
    );
  }
}
```

---

## 5. Route Sheet Page

### route_sheet_page.dart

```dart
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import '../../domain/bloc/route_sheet/route_sheet_bloc.dart';
import '../../domain/bloc/route_sheet/route_sheet_event.dart';
import '../../domain/bloc/route_sheet/route_sheet_state.dart';
import '../../data/models/task.dart';
import 'widgets/task_card.dart';
import 'widgets/complete_task_dialog.dart';
import 'widgets/reschedule_dialog.dart';
import 'widgets/task_detail_dialog.dart';

class RouteSheetPage extends StatefulWidget {
  final int? patientId;
  final bool isMyTasks; // true for caregivers viewing their own tasks

  const RouteSheetPage({
    super.key,
    this.patientId,
    this.isMyTasks = false,
  });

  @override
  State<RouteSheetPage> createState() => _RouteSheetPageState();
}

class _RouteSheetPageState extends State<RouteSheetPage> {
  late DateTime _selectedDate;

  @override
  void initState() {
    super.initState();
    _selectedDate = DateTime.now();
    _loadTasks();
  }

  void _loadTasks() {
    final bloc = context.read<RouteSheetBloc>();
    if (widget.isMyTasks) {
      bloc.add(LoadMyTasks(date: _selectedDate));
    } else {
      bloc.add(LoadTasks(patientId: widget.patientId, date: _selectedDate));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.isMyTasks ? 'Мои задачи' : 'Маршрутный лист'),
        actions: [
          if (!widget.isMyTasks)
            IconButton(
              icon: const Icon(Icons.add),
              onPressed: () => _showCreateTaskDialog(),
            ),
        ],
      ),
      body: BlocConsumer<RouteSheetBloc, RouteSheetState>(
        listener: (context, state) {
          if (state.errorMessage != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.errorMessage!),
                backgroundColor: Colors.red,
              ),
            );
          }
          if (state.successMessage != null) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.successMessage!),
                backgroundColor: Colors.green,
              ),
            );
          }
        },
        builder: (context, state) {
          return Column(
            children: [
              _buildDateSelector(state),
              _buildSummary(state),
              Expanded(
                child: state.isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : _buildTasksList(state),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildDateSelector(RouteSheetState state) {
    return Container(
      padding: const EdgeInsets.all(16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          IconButton(
            icon: const Icon(Icons.chevron_left),
            onPressed: () {
              setState(() {
                _selectedDate = _selectedDate.subtract(const Duration(days: 1));
              });
              context.read<RouteSheetBloc>().add(ChangeDate(_selectedDate));
            },
          ),
          GestureDetector(
            onTap: () async {
              final date = await showDatePicker(
                context: context,
                initialDate: _selectedDate,
                firstDate: DateTime.now().subtract(const Duration(days: 365)),
                lastDate: DateTime.now().add(const Duration(days: 365)),
              );
              if (date != null) {
                setState(() => _selectedDate = date);
                context.read<RouteSheetBloc>().add(ChangeDate(date));
              }
            },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: Theme.of(context).primaryColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  const Icon(Icons.calendar_today, size: 18),
                  const SizedBox(width: 8),
                  Text(
                    DateFormat('d MMMM yyyy', 'ru').format(_selectedDate),
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.chevron_right),
            onPressed: () {
              setState(() {
                _selectedDate = _selectedDate.add(const Duration(days: 1));
              });
              context.read<RouteSheetBloc>().add(ChangeDate(_selectedDate));
            },
          ),
        ],
      ),
    );
  }

  Widget _buildSummary(RouteSheetState state) {
    if (state.summary == null) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.grey[100],
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _buildSummaryItem('Всего', state.summary!.total, Colors.grey),
          _buildSummaryItem('Ожидают', state.summary!.pending, Colors.blue),
          _buildSummaryItem('Выполнено', state.summary!.completed, Colors.green),
          _buildSummaryItem('Пропущено', state.summary!.missed, Colors.red),
        ],
      ),
    );
  }

  Widget _buildSummaryItem(String label, int count, Color color) {
    return Column(
      children: [
        Text(
          count.toString(),
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }

  Widget _buildTasksList(RouteSheetState state) {
    if (state.tasks.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.task_alt, size: 64, color: Colors.grey[300]),
            const SizedBox(height: 16),
            Text(
              'Нет задач на этот день',
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 16,
              ),
            ),
          ],
        ),
      );
    }

    // Group tasks by time
    final groupedTasks = <String, List<Task>>{};
    for (var task in state.tasks) {
      final hour = '${task.startAt.hour.toString().padLeft(2, '0')}:00';
      groupedTasks.putIfAbsent(hour, () => []);
      groupedTasks[hour]!.add(task);
    }

    final sortedHours = groupedTasks.keys.toList()..sort();

    return RefreshIndicator(
      onRefresh: () async {
        context.read<RouteSheetBloc>().add(RefreshTasks());
      },
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: sortedHours.length,
        itemBuilder: (context, index) {
          final hour = sortedHours[index];
          final tasks = groupedTasks[hour]!;

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(
                  children: [
                    SizedBox(
                      width: 50,
                      child: Text(
                        hour,
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          color: Colors.grey[700],
                        ),
                      ),
                    ),
                    Expanded(child: Divider(color: Colors.grey[300])),
                  ],
                ),
              ),
              ...tasks.map((task) => TaskCard(
                    task: task,
                    onTap: () => _showTaskDetail(task),
                    onComplete: task.isPending
                        ? () => _showCompleteDialog(task)
                        : null,
                  )),
            ],
          );
        },
      ),
    );
  }

  void _showTaskDetail(Task task) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => TaskDetailDialog(
        task: task,
        onComplete: () {
          Navigator.pop(context);
          _showCompleteDialog(task);
        },
        onMiss: () {
          Navigator.pop(context);
          _showMissDialog(task);
        },
        onReschedule: () {
          Navigator.pop(context);
          _showRescheduleDialog(task);
        },
      ),
    );
  }

  void _showCompleteDialog(Task task) {
    showDialog(
      context: context,
      builder: (context) => CompleteTaskDialog(
        task: task,
        onComplete: (comment, photos, value) {
          context.read<RouteSheetBloc>().add(CompleteTask(
                taskId: task.id,
                comment: comment,
                photos: photos,
                value: value,
              ));
          Navigator.pop(context);
        },
      ),
    );
  }

  void _showMissDialog(Task task) {
    final controller = TextEditingController();
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Причина невыполнения'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            hintText: 'Укажите причину...',
            border: OutlineInputBorder(),
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Отмена'),
          ),
          ElevatedButton(
            onPressed: () {
              if (controller.text.isNotEmpty) {
                context.read<RouteSheetBloc>().add(MissTask(
                      taskId: task.id,
                      reason: controller.text,
                    ));
                Navigator.pop(context);
              }
            },
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Подтвердить'),
          ),
        ],
      ),
    );
  }

  void _showRescheduleDialog(Task task) {
    showDialog(
      context: context,
      builder: (context) => RescheduleDialog(
        task: task,
        onReschedule: (startAt, endAt, reason) {
          context.read<RouteSheetBloc>().add(RescheduleTask(
                taskId: task.id,
                startAt: startAt,
                endAt: endAt,
                reason: reason,
              ));
          Navigator.pop(context);
        },
      ),
    );
  }

  void _showCreateTaskDialog() {
    // Navigate to create task page
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => CreateTaskPage(patientId: widget.patientId!),
      ),
    );
  }
}
```

---

## Дополнительные диалоги и страницы см. в следующих файлах...

Документация продолжается в части 2.

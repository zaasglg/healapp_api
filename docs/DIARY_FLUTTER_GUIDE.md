# 📱 Руководство по созданию модуля Дневника (Flutter)

## Содержание

1. [Обзор модуля](#обзор-модуля)
2. [Архитектура](#архитектура)
3. [API Endpoints](#api-endpoints)
4. [Модели данных](#модели-данных)
5. [Типы показателей](#типы-показателей)
6. [Реализация Data Layer](#реализация-data-layer)
7. [Реализация Domain Layer](#реализация-domain-layer)
8. [Реализация Presentation Layer](#реализация-presentation-layer)
9. [UI Компоненты](#ui-компоненты)
10. [Примеры использования](#примеры-использования)

---

## Обзор модуля

Модуль **Дневника подопечного** — это цифровой журнал для фиксации показателей здоровья пациента. Включает:

- ✅ Создание дневника для пациента
- ✅ Добавление записей показателей (4 типа)
- ✅ Закреплённые показатели с таймерами
- ✅ Просмотр истории записей
- ✅ Графики динамики показателей

---

## Архитектура

Используем **Clean Architecture** с разделением на 3 слоя:

```
lib/features/diary/
├── data/                          # Слой данных
│   ├── datasources/
│   │   └── diary_remote_datasource.dart
│   ├── models/
│   │   ├── diary_model.dart
│   │   ├── diary_entry_model.dart
│   │   └── pinned_parameter_model.dart
│   └── repositories/
│       └── diary_repository_impl.dart
│
├── domain/                        # Слой бизнес-логики
│   ├── entities/
│   │   ├── diary.dart
│   │   ├── diary_entry.dart
│   │   └── pinned_parameter.dart
│   ├── repositories/
│   │   └── diary_repository.dart
│   └── usecases/
│       ├── create_diary.dart
│       ├── get_diary.dart
│       ├── add_diary_entry.dart
│       └── update_pinned_parameters.dart
│
└── presentation/                  # Слой представления
    ├── bloc/
    │   ├── diary_bloc.dart
    │   ├── diary_event.dart
    │   └── diary_state.dart
    ├── pages/
    │   ├── diary_page.dart
    │   ├── add_entry_page.dart
    │   └── diary_chart_page.dart
    └── widgets/
        ├── pinned_parameters_section.dart
        ├── diary_entry_card.dart
        ├── parameter_blocks_section.dart
        └── entry_input_forms/
            ├── blood_pressure_form.dart
            ├── temperature_form.dart
            └── ...
```

---

## API Endpoints

**Base URL**: `https://your-api-url.com/api/v1`

**Headers**:
```
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json
```

### 1. Создать дневник

```http
POST /diary/create
```

**Request Body**:
```json
{
  "patient_id": 1,
  "pinned_parameters": [
    {"key": "blood_pressure", "interval_minutes": 60},
    {"key": "temperature", "interval_minutes": 120}
  ],
  "settings": null
}
```

**Response (201)**:
```json
{
  "id": 1,
  "patient_id": 1,
  "pinned_parameters": [
    {"key": "blood_pressure", "interval_minutes": 60},
    {"key": "temperature", "interval_minutes": 120}
  ],
  "settings": null,
  "entries": [],
  "created_at": "2024-12-18T10:00:00.000000Z",
  "updated_at": "2024-12-18T10:00:00.000000Z"
}
```

**Response (409)** — если дневник уже существует:
```json
{
  "message": "Diary already exists for this patient",
  "diary_id": 1
}
```

---

### 2. Получить дневник

```http
GET /diary?patient_id=1&from_date=2024-12-01&to_date=2024-12-18
```

**Query Parameters**:
| Параметр | Тип | Обязателен | Описание |
|----------|-----|------------|----------|
| patient_id | int | ✅ | ID пациента |
| from_date | string | ❌ | Начальная дата (YYYY-MM-DD) |
| to_date | string | ❌ | Конечная дата (YYYY-MM-DD) |

**Response (200)**:
```json
{
  "id": 1,
  "patient_id": 1,
  "pinned_parameters": [
    {"key": "blood_pressure", "interval_minutes": 60, "last_recorded_at": null}
  ],
  "settings": null,
  "entries": [
    {
      "id": 1,
      "diary_id": 1,
      "author_id": 1,
      "type": "physical",
      "key": "blood_pressure",
      "value": {"systolic": 120, "diastolic": 80},
      "notes": "После обеда",
      "recorded_at": "2024-12-18T14:30:00.000000Z",
      "created_at": "2024-12-18T14:30:00.000000Z",
      "updated_at": "2024-12-18T14:30:00.000000Z"
    }
  ],
  "created_at": "2024-12-18T10:00:00.000000Z",
  "updated_at": "2024-12-18T14:30:00.000000Z"
}
```

---

### 3. Добавить запись

```http
POST /diary
```

**Request Body**:
```json
{
  "patient_id": 1,
  "type": "physical",
  "key": "blood_pressure",
  "value": {"systolic": 120, "diastolic": 80},
  "notes": "Измерение после обеда",
  "recorded_at": "2024-12-18T14:30:00Z"
}
```

**Response (201)**:
```json
{
  "id": 1,
  "diary_id": 1,
  "author_id": 1,
  "type": "physical",
  "key": "blood_pressure",
  "value": {"systolic": 120, "diastolic": 80},
  "notes": "Измерение после обеда",
  "recorded_at": "2024-12-18T14:30:00.000000Z",
  "created_at": "2024-12-18T14:30:00.000000Z",
  "updated_at": "2024-12-18T14:30:00.000000Z"
}
```

---

### 4. Обновить закреплённые показатели

```http
PATCH /diary/pinned
```

**Request Body**:
```json
{
  "patient_id": 1,
  "pinned_parameters": [
    {"key": "blood_pressure", "interval_minutes": 30},
    {"key": "temperature", "interval_minutes": 60},
    {"key": "pulse", "interval_minutes": 120}
  ]
}
```

**Response (200)**:
```json
{
  "message": "Pinned parameters updated successfully",
  "diary": {
    "id": 1,
    "patient_id": 1,
    "pinned_parameters": [...],
    "settings": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

### 5. Получить данные для графика

```http
GET /stats/chart?patient_id=1&key=blood_pressure&period=7_days
```

**Query Parameters**:
| Параметр | Тип | Обязателен | Описание |
|----------|-----|------------|----------|
| patient_id | int | ✅ | ID пациента |
| key | string | ✅ | Ключ показателя (например: `blood_pressure`) |
| period | string | ❌ | `7_days` (по умолчанию) или `30_days` |

**Response (200)**:
```json
{
  "patient_id": 1,
  "key": "blood_pressure",
  "period": "7_days",
  "data": [
    {
      "id": 1,
      "recorded_at": "2024-12-18T08:00:00Z",
      "value": {"systolic": 120, "diastolic": 80},
      "notes": null
    },
    {
      "id": 2,
      "recorded_at": "2024-12-18T14:00:00Z",
      "value": {"systolic": 125, "diastolic": 82},
      "notes": "После прогулки"
    }
  ]
}
```

---

## Модели данных

### Diary Entity

```dart
import 'package:equatable/equatable.dart';

class Diary extends Equatable {
  final int id;
  final int patientId;
  final List<PinnedParameter> pinnedParameters;
  final Map<String, dynamic>? settings;
  final List<DiaryEntry> entries;
  final DateTime createdAt;
  final DateTime updatedAt;

  const Diary({
    required this.id,
    required this.patientId,
    required this.pinnedParameters,
    this.settings,
    required this.entries,
    required this.createdAt,
    required this.updatedAt,
  });

  @override
  List<Object?> get props => [id, patientId, pinnedParameters, settings, entries];
}
```

### DiaryEntry Entity

```dart
import 'package:equatable/equatable.dart';

enum DiaryEntryType { care, physical, excretion, symptom }

class DiaryEntry extends Equatable {
  final int id;
  final int diaryId;
  final int authorId;
  final DiaryEntryType type;
  final String key;
  final Map<String, dynamic> value;
  final String? notes;
  final DateTime recordedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  const DiaryEntry({
    required this.id,
    required this.diaryId,
    required this.authorId,
    required this.type,
    required this.key,
    required this.value,
    this.notes,
    required this.recordedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  @override
  List<Object?> get props => [id, diaryId, type, key, value, recordedAt];
}
```

### PinnedParameter Entity

```dart
import 'package:equatable/equatable.dart';

class PinnedParameter extends Equatable {
  final String key;
  final int intervalMinutes;
  final DateTime? lastRecordedAt;

  const PinnedParameter({
    required this.key,
    required this.intervalMinutes,
    this.lastRecordedAt,
  });

  /// Возвращает оставшееся время до следующего замера
  Duration get timeUntilNext {
    if (lastRecordedAt == null) return Duration.zero;
    final nextTime = lastRecordedAt!.add(Duration(minutes: intervalMinutes));
    final remaining = nextTime.difference(DateTime.now());
    return remaining.isNegative ? Duration.zero : remaining;
  }

  /// Истёк ли таймер
  bool get isOverdue => timeUntilNext == Duration.zero;

  @override
  List<Object?> get props => [key, intervalMinutes, lastRecordedAt];
}
```

---

## Типы показателей

### 1. **physical** — Физикальные параметры

| key | value | Иконка | Описание |
|-----|-------|--------|----------|
| `temperature` | `{"value": 36.6}` | 🌡️ | Температура тела (°C) |
| `blood_pressure` | `{"systolic": 120, "diastolic": 80}` | 💓 | Артериальное давление |
| `pulse` | `{"value": 75}` | ❤️ | Пульс (уд/мин) |
| `saturation` | `{"value": 98}` | 🫁 | Сатурация SpO2 (%) |
| `blood_sugar` | `{"value": 5.5}` | 🩸 | Глюкоза крови (ммоль/л) |
| `respiratory_rate` | `{"value": 16}` | 💨 | Частота дыхания (в мин) |
| `weight` | `{"value": 70}` | ⚖️ | Вес (кг) |

### 2. **care** — Уход

| key | value | Иконка | Описание |
|-----|-------|--------|----------|
| `meal` | `{"type": "breakfast", "eaten": true, "amount": "full"}` | 🍽️ | Приём пищи |
| `medicine` | `{"name": "Аспирин", "dose": "100mg", "taken": true}` | 💊 | Приём лекарств |
| `vitamins` | `{"name": "Витамин D", "taken": true}` | 💎 | Витамины |
| `diaper_change` | `{"done": true, "type": "wet"}` | 🧷 | Смена подгузника |
| `hygiene` | `{"type": "bath", "done": true}` | 🚿 | Гигиенические процедуры |
| `skin_moisturizing` | `{"done": true, "area": "body"}` | 🧴 | Увлажнение кожи |
| `walk` | `{"duration_minutes": 30}` | 🚶 | Прогулка |
| `cognitive_games` | `{"type": "puzzle", "duration_minutes": 20}` | 🧩 | Когнитивные игры |
| `sleep` | `{"hours": 8, "quality": "good"}` | 😴 | Сон |

### 3. **excretion** — Выделения

| key | value | Иконка | Описание |
|-----|-------|--------|----------|
| `urination` | `{"occurred": true, "color": "normal", "notes": ""}` | 💧 | Мочеиспускание |
| `defecation` | `{"occurred": true, "consistency": "normal", "color": "brown"}` | 💩 | Дефекация |

### 4. **symptom** — Тягостные симптомы

| key | value | Иконка | Описание |
|-----|-------|--------|----------|
| `pain_level` | `{"level": 3, "location": "head"}` | 😣 | Уровень боли (0-10) |
| `nausea` | `{"occurred": true, "severity": "mild"}` | 🤢 | Тошнота |
| `vomiting` | `{"occurred": true, "times": 1}` | 🤮 | Рвота |
| `dyspnea` | `{"occurred": true, "severity": "mild"}` | 😮‍💨 | Одышка |
| `itching` | `{"occurred": true, "location": "arms"}` | 🤚 | Зуд |
| `cough` | `{"type": "dry", "intensity": "mild"}` | 😷 | Кашель |
| `dry_mouth` | `{"occurred": true}` | 👄 | Сухость во рту |
| `hiccups` | `{"occurred": true, "duration_minutes": 5}` | 🫢 | Икота |
| `taste_disorder` | `{"occurred": true, "type": "metallic"}` | 👅 | Нарушение вкуса |

---

## Реализация Data Layer

### DiaryModel

```dart
import 'package:json_annotation/json_annotation.dart';
import '../../domain/entities/diary.dart';

part 'diary_model.g.dart';

@JsonSerializable()
class DiaryModel {
  final int id;
  @JsonKey(name: 'patient_id')
  final int patientId;
  @JsonKey(name: 'pinned_parameters')
  final List<PinnedParameterModel>? pinnedParameters;
  final Map<String, dynamic>? settings;
  final List<DiaryEntryModel>? entries;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  DiaryModel({
    required this.id,
    required this.patientId,
    this.pinnedParameters,
    this.settings,
    this.entries,
    required this.createdAt,
    required this.updatedAt,
  });

  factory DiaryModel.fromJson(Map<String, dynamic> json) =>
      _$DiaryModelFromJson(json);

  Map<String, dynamic> toJson() => _$DiaryModelToJson(this);

  Diary toEntity() => Diary(
        id: id,
        patientId: patientId,
        pinnedParameters:
            pinnedParameters?.map((e) => e.toEntity()).toList() ?? [],
        settings: settings,
        entries: entries?.map((e) => e.toEntity()).toList() ?? [],
        createdAt: createdAt,
        updatedAt: updatedAt,
      );
}
```

### DiaryRemoteDataSource

```dart
import 'package:dio/dio.dart';

abstract class DiaryRemoteDataSource {
  Future<DiaryModel> createDiary({
    required int patientId,
    List<Map<String, dynamic>>? pinnedParameters,
  });

  Future<DiaryModel> getDiary({
    required int patientId,
    String? fromDate,
    String? toDate,
  });

  Future<DiaryEntryModel> addEntry({
    required int patientId,
    required String type,
    required String key,
    required Map<String, dynamic> value,
    String? notes,
    required DateTime recordedAt,
  });

  Future<DiaryModel> updatePinnedParameters({
    required int patientId,
    required List<Map<String, dynamic>> pinnedParameters,
  });

  Future<ChartDataModel> getChartData({
    required int patientId,
    required String key,
    String period = '7_days',
  });
}

class DiaryRemoteDataSourceImpl implements DiaryRemoteDataSource {
  final Dio dio;

  DiaryRemoteDataSourceImpl({required this.dio});

  @override
  Future<DiaryModel> createDiary({
    required int patientId,
    List<Map<String, dynamic>>? pinnedParameters,
  }) async {
    final response = await dio.post(
      '/diary/create',
      data: {
        'patient_id': patientId,
        'pinned_parameters': pinnedParameters,
      },
    );
    return DiaryModel.fromJson(response.data);
  }

  @override
  Future<DiaryModel> getDiary({
    required int patientId,
    String? fromDate,
    String? toDate,
  }) async {
    final response = await dio.get(
      '/diary',
      queryParameters: {
        'patient_id': patientId,
        if (fromDate != null) 'from_date': fromDate,
        if (toDate != null) 'to_date': toDate,
      },
    );
    return DiaryModel.fromJson(response.data);
  }

  @override
  Future<DiaryEntryModel> addEntry({
    required int patientId,
    required String type,
    required String key,
    required Map<String, dynamic> value,
    String? notes,
    required DateTime recordedAt,
  }) async {
    final response = await dio.post(
      '/diary',
      data: {
        'patient_id': patientId,
        'type': type,
        'key': key,
        'value': value,
        'notes': notes,
        'recorded_at': recordedAt.toIso8601String(),
      },
    );
    return DiaryEntryModel.fromJson(response.data);
  }

  @override
  Future<DiaryModel> updatePinnedParameters({
    required int patientId,
    required List<Map<String, dynamic>> pinnedParameters,
  }) async {
    final response = await dio.patch(
      '/diary/pinned',
      data: {
        'patient_id': patientId,
        'pinned_parameters': pinnedParameters,
      },
    );
    return DiaryModel.fromJson(response.data['diary']);
  }

  @override
  Future<ChartDataModel> getChartData({
    required int patientId,
    required String key,
    String period = '7_days',
  }) async {
    final response = await dio.get(
      '/stats/chart',
      queryParameters: {
        'patient_id': patientId,
        'key': key,
        'period': period,
      },
    );
    return ChartDataModel.fromJson(response.data);
  }
}
```

---

## Реализация Domain Layer

### DiaryRepository (Abstract)

```dart
import 'package:dartz/dartz.dart';
import '../entities/diary.dart';
import '../entities/diary_entry.dart';

abstract class DiaryRepository {
  Future<Either<Failure, Diary>> createDiary({
    required int patientId,
    List<PinnedParameter>? pinnedParameters,
  });

  Future<Either<Failure, Diary>> getDiary({
    required int patientId,
    DateTime? fromDate,
    DateTime? toDate,
  });

  Future<Either<Failure, DiaryEntry>> addEntry({
    required int patientId,
    required DiaryEntryType type,
    required String key,
    required Map<String, dynamic> value,
    String? notes,
    required DateTime recordedAt,
  });

  Future<Either<Failure, Diary>> updatePinnedParameters({
    required int patientId,
    required List<PinnedParameter> pinnedParameters,
  });

  Future<Either<Failure, ChartData>> getChartData({
    required int patientId,
    required String key,
    String period,
  });
}
```

### Use Cases

```dart
// create_diary.dart
class CreateDiary {
  final DiaryRepository repository;

  CreateDiary(this.repository);

  Future<Either<Failure, Diary>> call(CreateDiaryParams params) {
    return repository.createDiary(
      patientId: params.patientId,
      pinnedParameters: params.pinnedParameters,
    );
  }
}

class CreateDiaryParams extends Equatable {
  final int patientId;
  final List<PinnedParameter>? pinnedParameters;

  const CreateDiaryParams({
    required this.patientId,
    this.pinnedParameters,
  });

  @override
  List<Object?> get props => [patientId, pinnedParameters];
}
```

---

## Реализация Presentation Layer

### DiaryBloc

```dart
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:freezed_annotation/freezed_annotation.dart';

part 'diary_bloc.freezed.dart';

// Events
@freezed
class DiaryEvent with _$DiaryEvent {
  const factory DiaryEvent.loadDiary({required int patientId}) = _LoadDiary;
  const factory DiaryEvent.createDiary({
    required int patientId,
    List<PinnedParameter>? pinnedParameters,
  }) = _CreateDiary;
  const factory DiaryEvent.addEntry({
    required int patientId,
    required DiaryEntryType type,
    required String key,
    required Map<String, dynamic> value,
    String? notes,
    required DateTime recordedAt,
  }) = _AddEntry;
  const factory DiaryEvent.updatePinnedParameters({
    required int patientId,
    required List<PinnedParameter> pinnedParameters,
  }) = _UpdatePinnedParameters;
}

// States
@freezed
class DiaryState with _$DiaryState {
  const factory DiaryState.initial() = _Initial;
  const factory DiaryState.loading() = _Loading;
  const factory DiaryState.loaded({required Diary diary}) = _Loaded;
  const factory DiaryState.error({required String message}) = _Error;
  const factory DiaryState.entryAdded({required DiaryEntry entry}) = _EntryAdded;
}

// Bloc
class DiaryBloc extends Bloc<DiaryEvent, DiaryState> {
  final GetDiary getDiary;
  final CreateDiary createDiary;
  final AddDiaryEntry addDiaryEntry;
  final UpdatePinnedParameters updatePinnedParameters;

  DiaryBloc({
    required this.getDiary,
    required this.createDiary,
    required this.addDiaryEntry,
    required this.updatePinnedParameters,
  }) : super(const DiaryState.initial()) {
    on<_LoadDiary>(_onLoadDiary);
    on<_CreateDiary>(_onCreateDiary);
    on<_AddEntry>(_onAddEntry);
    on<_UpdatePinnedParameters>(_onUpdatePinnedParameters);
  }

  Future<void> _onLoadDiary(_LoadDiary event, Emitter<DiaryState> emit) async {
    emit(const DiaryState.loading());
    
    final result = await getDiary(GetDiaryParams(patientId: event.patientId));
    
    result.fold(
      (failure) => emit(DiaryState.error(message: failure.message)),
      (diary) => emit(DiaryState.loaded(diary: diary)),
    );
  }

  Future<void> _onAddEntry(_AddEntry event, Emitter<DiaryState> emit) async {
    emit(const DiaryState.loading());
    
    final result = await addDiaryEntry(AddDiaryEntryParams(
      patientId: event.patientId,
      type: event.type,
      key: event.key,
      value: event.value,
      notes: event.notes,
      recordedAt: event.recordedAt,
    ));
    
    result.fold(
      (failure) => emit(DiaryState.error(message: failure.message)),
      (entry) => emit(DiaryState.entryAdded(entry: entry)),
    );
  }

  // ... остальные обработчики
}
```

---

## UI Компоненты

### Главная страница дневника

```dart
class DiaryPage extends StatelessWidget {
  final int patientId;

  const DiaryPage({super.key, required this.patientId});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => getIt<DiaryBloc>()
        ..add(DiaryEvent.loadDiary(patientId: patientId)),
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Дневник'),
          actions: [
            IconButton(
              icon: const Icon(Icons.bar_chart),
              onPressed: () => _openCharts(context),
            ),
          ],
        ),
        body: BlocBuilder<DiaryBloc, DiaryState>(
          builder: (context, state) {
            return state.when(
              initial: () => const SizedBox.shrink(),
              loading: () => const Center(child: CircularProgressIndicator()),
              loaded: (diary) => _DiaryContent(diary: diary),
              error: (message) => Center(child: Text(message)),
              entryAdded: (entry) => const SizedBox.shrink(),
            );
          },
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: () => _addEntry(context),
          child: const Icon(Icons.add),
        ),
      ),
    );
  }
}

class _DiaryContent extends StatelessWidget {
  final Diary diary;

  const _DiaryContent({required this.diary});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      slivers: [
        // Закреплённые показатели
        SliverToBoxAdapter(
          child: PinnedParametersSection(
            parameters: diary.pinnedParameters,
            onTap: (key) => _addEntryForKey(context, key),
          ),
        ),
        
        // Блоки показателей
        SliverToBoxAdapter(
          child: ParameterBlocksSection(
            onBlockTap: (type) => _addEntryForType(context, type),
          ),
        ),
        
        // История записей
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Text(
              'История за сегодня',
              style: Theme.of(context).textTheme.titleLarge,
            ),
          ),
        ),
        
        SliverList(
          delegate: SliverChildBuilderDelegate(
            (context, index) => DiaryEntryCard(entry: diary.entries[index]),
            childCount: diary.entries.length,
          ),
        ),
      ],
    );
  }
}
```

### Виджет закреплённых показателей

```dart
class PinnedParametersSection extends StatelessWidget {
  final List<PinnedParameter> parameters;
  final Function(String key) onTap;

  const PinnedParametersSection({
    super.key,
    required this.parameters,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    if (parameters.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            'Закреплённые показатели',
            style: Theme.of(context).textTheme.titleMedium,
          ),
        ),
        SizedBox(
          height: 120,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            itemCount: parameters.length,
            itemBuilder: (context, index) {
              final param = parameters[index];
              return _PinnedParameterCard(
                parameter: param,
                onTap: () => onTap(param.key),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _PinnedParameterCard extends StatelessWidget {
  final PinnedParameter parameter;
  final VoidCallback onTap;

  const _PinnedParameterCard({
    required this.parameter,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final isOverdue = parameter.isOverdue;
    
    return Card(
      color: isOverdue ? Colors.red.shade50 : null,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 140,
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                _getIconForKey(parameter.key),
                color: isOverdue ? Colors.red : Colors.blue,
              ),
              const SizedBox(height: 8),
              Text(
                _getLabelForKey(parameter.key),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              const Spacer(),
              if (isOverdue)
                const Text(
                  'Время замера!',
                  style: TextStyle(color: Colors.red, fontSize: 12),
                )
              else
                _CountdownTimer(duration: parameter.timeUntilNext),
            ],
          ),
        ),
      ),
    );
  }
}
```

---

## Примеры использования

### Создание дневника при создании пациента

```dart
Future<void> createPatientWithDiary() async {
  // 1. Создаём пациента
  final patient = await patientRepository.createPatient(...);
  
  // 2. Создаём дневник с закреплёнными показателями
  await diaryRepository.createDiary(
    patientId: patient.id,
    pinnedParameters: [
      PinnedParameter(key: 'blood_pressure', intervalMinutes: 60),
      PinnedParameter(key: 'temperature', intervalMinutes: 120),
    ],
  );
}
```

### Добавление записи

```dart
void addBloodPressureReading() {
  context.read<DiaryBloc>().add(
    DiaryEvent.addEntry(
      patientId: currentPatientId,
      type: DiaryEntryType.physical,
      key: 'blood_pressure',
      value: {'systolic': 120, 'diastolic': 80},
      notes: 'Измерение после обеда',
      recordedAt: DateTime.now(),
    ),
  );
}
```

---

## Важные замечания

1. **Таймеры закреплённых показателей** — используй `Timer.periodic` для обновления обратного отсчёта каждую секунду.

2. **Локальное кэширование** — используй Hive для кэширования данных дневника и работы офлайн.

3. **Push уведомления** — интегрируй FCM для напоминаний о замерах.

4. **Графики** — используй пакет `fl_chart` для визуализации данных.

5. **Формы ввода** — создай отдельный виджет формы для каждого типа показателя с валидацией.

---

**Автор**: HealApp API  
**Версия**: 1.0  
**Дата**: 2024-12-18

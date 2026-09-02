<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/** Нормализует данные универсальных административных форм по декларативной схеме. */
final class FormValidator
{
    /** @param array<int, array<string, mixed>> $fields Описание полей формы. */
    public function __construct(private array $fields)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>} Валидированные данные и ошибки.
     */
    public function validate(array $input): array
    {
        $data = [];
        $errors = [];

        foreach ($this->fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $label = (string) ($field['label'] ?? $name);
            $type = (string) ($field['type'] ?? 'text');
            $required = (bool) ($field['required'] ?? false);

            $raw = $input[$name] ?? '';
            $value = is_string($raw) ? trim($raw) : $raw;

            if ($value === '') {
                if ($required) {
                    $errors[$name] = sprintf('Поле "%s" обязательно для заполнения.', $label);
                } else {
                    $data[$name] = null;
                }
                continue;
            }

            // Каждый тип одновременно преобразует значение и проверяет его ограничения.
            switch ($type) {
                case 'number':
                    $normalized = str_replace(',', '.', (string) $value);
                    if (!is_numeric($normalized)) {
                        $errors[$name] = sprintf('Поле "%s" должно быть числом.', $label);
                        break;
                    }
                    $numeric = (float) $normalized;
                    $valueType = (string) ($field['valueType'] ?? 'float');
                    if ($valueType === 'int' && (float) (int) $numeric !== $numeric) {
                        $errors[$name] = sprintf('Поле "%s" должно быть целым числом.', $label);
                        break;
                    }

                    $final = $valueType === 'int' ? (int) $numeric : $numeric;
                    if (isset($field['min']) && $final < (float) $field['min']) {
                        $errors[$name] = sprintf('Поле "%s" должно быть не меньше %s.', $label, $field['min']);
                        break;
                    }
                    if (isset($field['max']) && $final > (float) $field['max']) {
                        $errors[$name] = sprintf('Поле "%s" должно быть не больше %s.', $label, $field['max']);
                        break;
                    }

                    $data[$name] = $final;
                    break;
                case 'select':
                    $valueType = (string) ($field['valueType'] ?? 'int');
                    $converted = $valueType === 'string' ? (string) $value : (int) $value;
                    if (isset($field['options']) && is_array($field['options']) && $field['options'] !== []) {
                        $allowed = array_map('strval', array_keys($field['options']));
                        if (!in_array((string) $converted, $allowed, true)) {
                            $errors[$name] = sprintf('Поле "%s" содержит некорректное значение.', $label);
                            break;
                        }
                    }
                    $data[$name] = $converted;
                    break;
                case 'date':
                    $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
                    $dateErrors = DateTimeImmutable::getLastErrors();
                    if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                        $errors[$name] = sprintf('Поле "%s" должно быть датой.', $label);
                        break;
                    }
                    $data[$name] = $date->format('Y-m-d');
                    break;
                default:
                    $data[$name] = (string) $value;
            }
        }

        return [$data, $errors];
    }
}

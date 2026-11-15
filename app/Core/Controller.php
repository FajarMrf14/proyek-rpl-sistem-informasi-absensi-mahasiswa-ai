<?php

namespace App\Core;

class Controller
{
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Render view dengan layout wrapper
     *
     * @param string $viewPath Contoh: 'auth/login' atau 'mahasiswa/dashboard'
     * @param array $data Data yang diekstrak ke view
     * @param string|null $layout Nama file layout di app/Views/layouts/{layout}.php
     */
    protected function render(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        // Ekstrak variabel agar tersedia di dalam view dan layout
        extract($data);

        $viewFile = dirname(__DIR__) . '/Views/' . ltrim($viewPath, '/') . '.php';

        if (!file_exists($viewFile)) {
            die("View file tidak ditemukan: {$viewFile}");
        }

        // Tangkap konten view ke buffer
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Jika menggunakan layout wrapper
        if ($layout !== null) {
            $layoutFile = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    /**
     * Mengirim respons JSON
     */
    protected function json(mixed $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    /**
     * Redirect ke URL tujuan dengan opsi flash message
     */
    protected function redirect(string $url, ?array $flash = null): void
    {
        if ($flash !== null) {
            foreach ($flash as $type => $message) {
                Session::setFlash($type, $message);
            }
        }
        $this->response->redirect($url);
    }

    /**
     * Validasi input sederhana
     *
     * @param array $data Input data
     * @param array $rules Contoh: ['nim' => 'required|numeric', 'password' => 'required|min:6']
     * @return array [bool $isValid, array $errors]
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $ruleParam = null;
                if (str_contains($rule, ':')) {
                    list($rule, $ruleParam) = explode(':', $rule, 2);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || trim((string)$value) === '') {
                            $errors[$field][] = "Kolom " . ucfirst(str_replace('_', ' ', $field)) . " wajib diisi.";
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $errors[$field][] = "Kolom " . ucfirst(str_replace('_', ' ', $field)) . " harus berupa angka.";
                        }
                        break;

                    case 'min':
                        if ($value !== null && strlen((string)$value) < (int)$ruleParam) {
                            $errors[$field][] = "Kolom " . ucfirst(str_replace('_', ' ', $field)) . " minimal {$ruleParam} karakter.";
                        }
                        break;

                    case 'max':
                        if ($value !== null && strlen((string)$value) > (int)$ruleParam) {
                            $errors[$field][] = "Kolom " . ucfirst(str_replace('_', ' ', $field)) . " maksimal {$ruleParam} karakter.";
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', $ruleParam);
                        if ($value !== null && !in_array($value, $allowed)) {
                            $errors[$field][] = "Pilihan pada " . ucfirst(str_replace('_', ' ', $field)) . " tidak valid.";
                        }
                        break;
                }
            }
        }

        return [
            'isValid' => empty($errors),
            'errors'  => $errors
        ];
    }
}

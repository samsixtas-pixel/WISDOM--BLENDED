<?php
declare(strict_types=1);

namespace Wisdom\Repositories;

use Wisdom\Contracts\RepositoryInterface;
use Wisdom\Core\Database;
use Wisdom\Models\Exam;

final class ExamRepository implements RepositoryInterface
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?Exam
    {
        $row = $this->db->fetchRow('SELECT * FROM exams WHERE id = ? LIMIT 1', [$id]);

        return $row === null ? null : Exam::fromRow($row);
    }

    public function findByExamNumber(string $examNumber): ?Exam
    {
        $row = $this->db->fetchRow('SELECT * FROM exams WHERE exam_number = ? ORDER BY id ASC LIMIT 1', [$examNumber]);

        return $row === null ? null : Exam::fromRow($row);
    }

    /** @return list<Exam> */
    public function forUser(int $userId): array
    {
        return array_map(
            static fn (array $row): Exam => Exam::fromRow($row),
            $this->db->fetchAll('SELECT * FROM exams WHERE user_id = ? ORDER BY id', [$userId])
        );
    }

    /** Joined with the student's name for the admin table. */
    public function allWithStudent(int $limit = 300): array
    {
        return $this->db->fetchAll(
            'SELECT exams.*, users.name FROM exams JOIN users ON users.id = exams.user_id ORDER BY exams.id DESC LIMIT ?',
            [$limit]
        );
    }

    public function paginateWithStudent(\Wisdom\Core\ListQuery $query): array
    {
        $sorts = ['id' => 'exams.id', 'user' => 'users.name', 'subject_name' => 'exams.subject_name', 'subject_code' => 'exams.subject_code', 'result' => 'exams.result', 'status' => 'exams.status'];
        $sort = $sorts[$query->sort] ?? 'exams.id';
        $dir = $query->dir === 'asc' ? 'ASC' : 'DESC';
        $total = (int) $this->db->fetchValue('SELECT COUNT(*) FROM exams JOIN users ON users.id = exams.user_id');
        $rows = $this->db->fetchAll('SELECT exams.*, users.name FROM exams JOIN users ON users.id = exams.user_id' . " ORDER BY {$sort} {$dir} LIMIT ? OFFSET ?", [$query->perPage, $query->offset()]);
        return ['rows' => $rows, 'total' => $total, 'page' => $query->page, 'perPage' => $query->perPage];
    }

    public function create(int $userId, string $subjectCode, string $examNumber, string $subjectName, float $weight, ?float $result, ?string $grade, string $status): int
    {
        return $this->db->insert(
            'INSERT INTO exams (user_id, subject_code, exam_number, subject_name, weight, result, grade, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $subjectCode, $examNumber, $subjectName, $weight, $result, $grade, $status]
        );
    }

    public function update(int $id, float $weight, ?float $result, ?string $grade, string $status): bool
    {
        return $this->db->execute(
            'UPDATE exams SET weight = ?, result = ?, grade = ?, status = ? WHERE id = ?',
            [$weight, $result, $grade, $status, $id]
        ) > 0;
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM exams');
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM exams WHERE id = ?', [$id]) > 0;
    }
}

<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\AddComment;
use Fivexer\SDK\Model\Comment;
use Fivexer\SDK\Model\CommentPage;

/** Comments on a task, cursor-paginated newest-first. */
final class TaskComments
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function add(string $taskId, AddComment $input): Comment
    {
        $data = $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/comments', $input->toArray()) ?? [];
        return Comment::fromArray($data['comment'] ?? []);
    }

    /**
     * @param string|null $cursor Pagination cursor from a previous page's nextCursor
     * @param int<1, max>|null $limit Page size
     */
    public function list(string $taskId, ?string $cursor = null, ?int $limit = null): CommentPage
    {
        $data = $this->client->request('GET', '/tasks/' . \rawurlencode($taskId) . '/comments', null, [
            'cursor' => $cursor,
            'limit' => $limit,
        ]) ?? [];
        return CommentPage::fromArray($data);
    }

    public function remove(string $taskId, string $commentId): void
    {
        $this->client->request(
            'DELETE',
            '/tasks/' . \rawurlencode($taskId) . '/comments/' . \rawurlencode($commentId)
        );
    }
}

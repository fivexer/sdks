import { Fivexer } from '@fivexer/sdk';

async function main() {
  const client = new Fivexer({
    baseUrl: process.env.FIVEXER_BASE_URL!,
    apiKey: process.env.FIVEXER_API_KEY!,
  });

  await client.workers.upsert({ id: 'agent_1', tags: ['english', 'billing'] });
  console.log('upserted worker: agent_1');

  const task = await client.tasks.create({
    tags: ['english', 'billing'],
    priority: 90,
  });
  console.log(`created task: ${task.id} (${task.status})`);

  const queue = await client.workers.queue('agent_1');
  console.log(`queue size: ${queue.taskIds?.length ?? 0}`);

  const decisions = await client.decisions.list({ taskId: task.id, limit: 10 });
  for (const d of decisions) {
    console.log(`decision: worker=${d.workerId} status=${d.status}`);
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

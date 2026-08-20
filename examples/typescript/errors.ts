import { Fivexer, FivexerApiError } from '@fivexer/sdk';

async function main() {
  const client = new Fivexer({
    baseUrl: process.env.FIVEXER_BASE_URL!,
    apiKey: process.env.FIVEXER_API_KEY!,
  });

  try {
    await client.tasks.create({ tags: ['english'] });
  } catch (e) {
    if (e instanceof FivexerApiError) {
      console.log(`API error: ${e.status} ${e.code}`);
      console.log('task rate remaining:', e.quota?.taskRateRemaining);
    } else {
      throw e;
    }
  }
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

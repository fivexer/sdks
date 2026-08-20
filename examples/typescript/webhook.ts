import { SIGNATURE_HEADER, verifyWebhookSignature } from '@fivexer/sdk/webhook';

const rawBody =
  '{"event":"task.matched","data":{"taskId":"task_8fk2","workerId":"agent_1"}}';
const secret = 'whsec_test_secret';
const header =
  't=1704067200000,v1=7f3b9c2e4d8a1f6e5b0c3d7a9e2f4b8c1d5e6a7f9b0c2d3e4f5a6b7c8d9e0f1a';

const ok = verifyWebhookSignature(secret, header, rawBody);
console.log('signature valid:', ok);
console.log('header name:', SIGNATURE_HEADER);

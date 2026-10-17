/**
 * Browser encoding APIs missing from Jest's jsdom environment.
 * Import before core-data (whose sync dependency uses TextEncoder at load time).
 */
import { TextEncoder, TextDecoder } from 'util';

global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

export const PAGE_SIZE = 10;

export function readPage(payload) {
  if (Array.isArray(payload)) return { items: payload, page: 1, lastPage: 1 };
  const result = Array.isArray(payload?.data) ? payload : payload?.data;
  if (!Array.isArray(result?.data)) throw new Error('The server returned an invalid list response.');
  const meta = result.meta || result;
  return { items: result.data, page: Number(meta.current_page) || 1, lastPage: Number(meta.last_page) || 1 };
}

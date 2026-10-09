// Scanner fixture; never imported by the application.
function unsafeEvaluation(source) {
  // ruleid: javascript-no-dynamic-evaluation
  return eval(source);
}

function safeParsing(source) {
  // ok: javascript-no-dynamic-evaluation
  return JSON.parse(source);
}

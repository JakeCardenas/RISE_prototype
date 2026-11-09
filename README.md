# RISE

Welcome to RISE! Keep a private photo record of cracks and surfaces around your home: add photos over time, plan follow-ups and compare how a spot looks. With an AI key, RISE can also describe a photo or compare two.

RISE helps you keep track, but it can’t tell you whether a crack is safe, and AI can be wrong. If you’re concerned, talk with a qualified professional.

## Get started

You’ll need PHP 8.3+, Composer and Node 20+.

```sh
composer run setup   # first time only
composer run dev
```

Then visit [http://localhost:8000](http://localhost:8000) and create an account.

Your photos stay private to your account. AI is optional and only runs when you ask: to turn it on, put your own `GEMINI_API_KEY` and a `GEMINI_MODEL` (such as `gemini-3.6-flash`) in `.env`, and never commit that file.

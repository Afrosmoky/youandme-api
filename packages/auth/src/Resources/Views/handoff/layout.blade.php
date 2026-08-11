{{--
  The one page a couple ever sees from this backend.

  Both mail links land here rather than in the app directly: a custom scheme in
  an email body is not reliably clickable (Gmail and Outlook render jaity://
  as plain text), and a 302 to one is refused by some browsers without a user
  gesture. So the link is https, the work happens server-side, and the button
  below carries them into the app — which is also the gesture browsers want.

  It has to read well with no app installed: that is the case where the button
  does nothing and the heading is the whole message.
--}}
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Ja i Ty</title>
    <style>
        :root { color-scheme: dark; }
        body {
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: #0b0b0d; color: #f3f1ec;
            font-family: Georgia, 'Times New Roman', serif;
            text-align: center; padding: 24px;
        }
        .card { max-width: 22rem; }
        .glyph { font-size: 2.5rem; color: #a67f31; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: normal; margin: 0 0 .75rem; }
        p { color: #9a9aa2; line-height: 1.5; margin: 0 0 2rem; }
        a.button {
            display: block; padding: .9rem 1.5rem;
            border: 1px solid #e0b24e; border-radius: 14px;
            color: #e0b24e; text-decoration: none; font-size: 1rem;
        }
        small { display: block; margin-top: 1.5rem; color: #7e7e86; font-size: .8rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="glyph">&#9829;</div>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        <a class="button" href="{{ $deepLink }}">{{ $action }}</a>
        <small>Jeśli nic się nie otworzyło, wróć do aplikacji ręcznie.</small>
    </div>
</body>
</html>

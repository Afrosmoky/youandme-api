<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stamp seed_key on the weekly rituals (r001-r033), matching rows by the body they carry today.
     *
     * This is the one and only moment the text is trusted as an identifier. It
     * works because production was verified against these exact strings before
     * the migration ran (33 of 33 matched, in all four pools); from here on
     * the key is what a row is known by and the text is free to change.
     *
     * Rituals were keyed on body rather than title because two of them share the
     * title "Tydzień zrozumienia". That workaround ends here: the key is the key,
     * and the titles may repeat all they like.
     *
     * Fails loudly rather than half-stamping: a body that no longer matches, or
     * one that matches twice, aborts the migration and the deploy with it. On a
     * fresh database it is a no-op — there are no rows yet, and the seeder writes
     * the keys itself.
     */
    private const MAP = [
        'r001' => 'Usiądźcie na przeciwko siebie i dajcie sobie co najmniej 2minuty czasu tylko i wyłącznie na kontakt wzrokowy. Powtórzcie rytuał codziennie o danej porze dnia.',
        'r002' => 'Przytulcie się do siebie skupiając tylko na oddechu. Postarajcie się wyrównać swoje oddechy i pobyć tak przez parę minut. Powtórzcie rytuał codziennie o danej porze dnia.',
        'r003' => 'Wymieńcie się rzeczami za które jesteście sobie wdzięczni danego dnia. Mogą być to najdrobniejsze rzeczy. Powtórzcie rytuał codziennie o danej porze dnia.',
        'r004' => 'W tym tygodniu macie jedno zadanie: Znajdźcie wspólne miłe wspomnienie i postarajcie się je odtworzyć.',
        'r005' => 'Codzienne pod koniec dnia dokończcie zdanie: Dziś czułem/łam się dla ciebie ważny/a, gdy...',
        'r006' => 'Dokończcie zdania, a następnie zamienieńcie się kolejnością: A: Czuję się... ,gdy robisz.... Wtedy potrzebuję... B: Czujesz się..., gdy robię... Potrzebujesz wtedy... Dziękuję, że o tym mówisz.',
        'r007' => 'Codzienne pod koniec dnia dokończcie zdanie: Dziękuje Ci dziś za...',
        'r008' => 'Codzienne pod koniec dnia dokończcie zdanie: Ciężko mi gdy..., ale doceniam, że...',
        'r009' => 'Wybierzcie po jednej drobnej obietnicy, którą złożycie partnerowi wypełniając ją przez najbliższy tydzień np. " W tym tygodniu obiecuje codziennie wysyłać do ciebie jedną miłą wiadomość"',
        'r010' => 'Codzienne po przebudzeniu powiedzcie lub napiszcie sobie wiadomość kończącą zdanie: "Dzisiaj trzymam kciuki za...."',
        'r011' => 'Wybierzcie jeden romantyczny element, który będzie codziennym rytuałem tego tygodnia np. codzienne odpalenie świeczki w czasie relaksu/codzienny komplement/codzienny masaż',
        'r012' => 'Poświęćcie codziennie 10 minut na wspólną aktywność np. gimnastykę',
        'r013' => 'Codziennie w jeden sytuacji słabości partnera wypowiedzcie lub napiszcie zdanie: ,,Jesteś wartościowy/a także, gdy… (np. się pomylisz)’’',
        'r014' => 'Codziennie opowiedzcie sobie o jednej sytuacji z dnia i w reakcji na historie użyjcie zdań: A: ,,Rozumiem, że możesz czuć (się) ….  Czego w tym momencie potrzebujesz? B: Potrzebuję… Postarajcie się zaspokoić wspólnie swoje potrzeby.',
        'r015' => 'Codziennie wypowiedzcie oboje zdania: ,,Przyznaję, że dziś miałeś/łaś rację w… Dobrze, że zwróciłeś/łaś na to uwagę.’’',
        'r016' => 'Codziennie zainicjujcie oboje kontakt fizyczny poza rutyną przywitania/pożegnania, nie prowadzący do seksu. (np. objęcie, odgarnięcie włosów, masaż ramion) Jeśli nie jesteście przy sobie codziennie wybierzcie w zamian czułe słowo.',
        'r017' => 'W tym tygodniu macie jedno zadanie: Wybierzcie się gdzieś bez celu, planu i szczególnych oczekiwań.',
        'r018' => 'Postarajcie się bez presji na konkretny efekt ,,zatrzymać’’ w momentach w który druga strona może potrzebować wsparcia bądź zrozumienia. Postarajcie się odpowiedzieć w tych momentach miłym słowem, bądź czynem częściej niż zwykle.',
        'r019' => 'Codziennie postarajcie się odpowiedzieć sobie na pytania: Co sprawiło dziś radość partnerowi? Co sprawiło dziś przykrość partnerowi? Podzielcie się przemyśleniami.',
        'r020' => 'Codziennie zapiszcie jedno zdanie, które mówi za co byliście dziś wdzięczni partnerowi. Zróbcie przegląd notatek po tygodniu.',
        'r021' => 'Codziennie postarajcie się zauważyć moment w którym poczuliście, że partner dodał wam skrzydeł. Dzielcie się swoimi obserwacjami, dziękując sobie za docenienie w takich sytuacjach.',
        'r022' => 'Codziennie postarajcie się zaobserwować jedną rzecz, którą partner zrobił dobrze. Pod koniec dnia podzielcie się obserwacją.',
        'r023' => 'Znajdźcie codziennie 5 minut czasu o konkretnie ustalonej godzinie, którą poświęcicie na wspólną ciszę w objęciach.',
        'r024' => 'Codziennie postarajcie się zrobić mały gest dla partnera (np. przygotowanie ulubionego napoju, drobna przysługa).',
        'r025' => 'Znajdźcie wcześniej niesprawdzany przepis i przyrządźcie go wspólnie w dowolny dzień. Nie skupiajcie się na rezultacie, a na wspólnie spędzonym czasie.',
        'r026' => 'Wybierzcie jeden, wspólny, cel który zrealizujecie małymi krokami do końca tygodnia.',
        'r027' => 'Wybierzcie charytatywny cel (niekoniecznie w finansowej formie) i zrealizujcie go wspólnie w tym tygodniu np. spacer z psem ze schroniska.',
        'r028' => 'Znajdźcie inspirację np. na internecie i stwórzcie coś wspólnie np. obraz na płótnie, magnes na lodówkę, figurkę z gliny. Możecie zrobić to w jeden dzień lub rozłożyć na parę.',
        'r029' => 'Wybierzcie się w kilka ładnych miejsc w swojej miejscowości lub okolicach tworząc ranking tych, które zaciekawiły was najbardziej.',
        'r030' => 'Zaplanujcie czas w dowolny dzień na wspólne odkrywanie smaków. Przygotujcie dla siebie kilka różnych przysmaków i odgadnijcie je z zakrytymi oczami.',
        'r031' => 'Wybierzcie aktywność logiczną, którą podejmiecie wspólnie w dany dzień np. gra w szachy, puzzle, krzyżówka, karty.',
        'r032' => 'Wybierzcie jedną piosenkę, którą będziecie puszczać codziennie przed snem.',
        'r033' => 'Wybierzcie przyjemny zapach, który będzie wam towarzyszył codziennie w ramach wspólnej chwili relaksu np. świecy, olejku. Zapamiętajcie ten rytuał i wracajcie do niego w momentach odpoczynku .',
    ];

    public function up(): void
    {
        $total = DB::table('rituals')->where('locale', 'pl')->count();

        if ($total === 0) {
            // Fresh database — nothing seeded yet.
            return;
        }

        // Checked before anything is written: if a body appears twice, the text
        // cannot say which row is meant, and the unique index would stop us
        // mid-way with a message about an index rather than about the content.
        $duplicated = DB::table('rituals')
            ->where('locale', 'pl')
            ->whereIn('body', array_values(self::MAP))
            ->select('body')
            ->groupBy('body')
            ->havingRaw('count(*) > 1')
            ->pluck('body');

        if ($duplicated->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Ritual deck backfill: %d entries appear more than once, so the text cannot identify a row. '
                .'First: %s',
                $duplicated->count(),
                mb_strimwidth((string) $duplicated->first(), 0, 70, '…'),
            ));
        }

        $matched = 0;

        foreach (self::MAP as $seedKey => $body) {
            if (DB::table('rituals')->where('seed_key', $seedKey)->exists()) {
                // Already stamped: a re-run, not a second identity.
                $matched++;

                continue;
            }

            $matched += DB::table('rituals')
                ->where('locale', 'pl')
                ->where('body', $body)
                ->whereNull('seed_key')
                ->update(['seed_key' => $seedKey]);
        }

        if ($matched !== count(self::MAP)) {
            throw new RuntimeException(sprintf(
                'Ritual deck backfill matched %d of %d rows by body. The database no longer holds the '
                .'text this migration was frozen against — stop and compare before going further.',
                $matched,
                count(self::MAP),
            ));
        }

        // The count above says the file was fully matched; this says the POOL was.
        // A row nobody knew about would otherwise survive without a key, invisible
        // to a seeder that only ever looks things up by one — and stay that way.
        $unkeyed = DB::table('rituals')
            ->where('locale', 'pl')
            ->whereNull('seed_key')
            ->count();

        if ($unkeyed > 0) {
            throw new RuntimeException(sprintf(
                'Ritual deck backfill left %d rows without a seed_key. They are not in the frozen map, so '
                .'something put them there outside the seed — look before going further.',
                $unkeyed,
            ));
        }
    }

    public function down(): void
    {
        DB::table('rituals')->whereIn('seed_key', array_keys(self::MAP))->update(['seed_key' => null]);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stamp seed_key on the session deck (q001-q100), matching rows by the body they carry today.
     *
     * This is the one and only moment the text is trusted as an identifier. It
     * works because production was verified against these exact strings before
     * the migration ran (100 of 100 matched, in all four pools); from here on
     * the key is what a row is known by and the text is free to change.
     *
     * The map below is FROZEN at the moment this migration was written and is
     * deliberately not read from questions_with_categories_pl.json: that file is
     * about to change (slice c rewrites twenty question bodies into neutral
     * forms), and a migration whose behaviour shifts with a content file is a
     * migration nobody can reason about a year from now.
     *
     * Fails loudly rather than half-stamping: a body that no longer matches, or
     * one that matches twice, aborts the migration and the deploy with it. On a
     * fresh database it is a no-op — there are no rows yet, and the seeder writes
     * the keys itself.
     */
    private const MAP = [
        'q001' => 'Wybierz niespodziankę, która uszczęśliwiłaby Cię najbardziej i taką, która zadowoliłaby Cię najmniej. Wyjaśnij dlaczego.',
        'q002' => 'Które z zachowań partnera/ki wprawia Cię w lepszy nastrój i sprawia, że czujesz się bezpiecznie? Kiedy ostatnio tak się poczułeś/aś i w jakiej sytuacji?',
        'q003' => 'Jakie różnice między wami sprawiają, że wasz związek staje się ciekawszy? W jakich sytuacjach wasza odmienność jest przydatna?',
        'q004' => 'Co przyciągnęło Cię do partnera/ki z początków waszej znajomości?',
        'q005' => 'Co na przestrzeni czasu wypracowaliście w waszym związku? Czego się nauczyłeś/aś przez ten czas o sobie?',
        'q006' => 'Gdyby ktoś spytał, jaka jest twoja druga połówka, jak byś ją opisał/a, zaczynając od charakteru, a kończąc na wyglądzie?',
        'q007' => 'W której części dnia masz najwięcej energii do działania? O jakiej porze najlepiej Ci się odpoczywa?',
        'q008' => 'Czy twój partner/ka wpływa na twoją pewność siebie? Jakie zachowanie partnera/ki Cię buduje i sprawia, że czujesz się wartościową osobą?',
        'q009' => 'Kelner w restauracji przynosi Ci zamówienie bez składnika, który domawiałeś/aś. Jaka będzie twoja pierwsza reakcja?',
        'q010' => 'Jak zachowanie partnera/ki i atmosfera w ciągu dnia wpływa u Ciebie na sferę seksualną?',
        'q011' => 'Które z poniższych rozwiązań mogłoby uczynić seks najbardziej satysfakcjonującym?',
        'q012' => 'Wymień minimum 5 rzeczy, za które podziwiasz swojego partnera/kę. Które z czynności przychodzą Ci z trudem, które jemu/jej idą świetnie?',
        'q013' => 'Jaką czynność partner/ka może wykonywać z zamkniętymi oczami? W jakich sytuacjach jest to przydatne?',
        'q014' => 'W jakim wydaniu twój partner/ka jest najatrakcyjniejszy/a? Możesz wziąć pod uwagę np. ubiór, fryzurę, makijaż, ale też zachowanie.',
        'q015' => 'Co wpływa na jakościowo dobrze spędzony czas we dwoje? Które ze wspólnych chwil wspominasz najlepiej i dlaczego?',
        'q016' => 'Jakie pasje, cele, marzenia bądź poglądy podzielacie? Wymień wszystkie wspólne punkty, które przychodzą Ci do głowy.',
        'q017' => 'Co pozwala Ci się zrelaksować po ciężkim dniu? Co może zrobić dla Ciebie partner/ka, a czego może unikać, gdy jesteś zmęczony/a lub zestresowany/a?',
        'q018' => 'Kto Cię inspiruje? Co wyróżnia tę osobę i w czym chciałbyś/chciałabyś ją naśladować? Może to być zarówno ktoś bliski, jak i nieznany Ci osobiście.',
        'q019' => 'Wymień rzeczy, których chciałbyś/chciałabyś dokonać przez najbliższe 5 lat.',
        'q020' => 'Dokończ zdania i je uzasadnij: Mój dzień staje się lepszy, kiedy… Mój ulubiony moment w ciągu dnia to… Czuję się przez Ciebie doceniony/a, gdy… Czuję, że mnie kochasz, gdy… Stresuję się, gdy… Czuję się ważny/a, gdy…',
        'q021' => 'Jak postrzegasz siebie w związku? Przez co jesteś dobrym partnerem, a nad czym powinieneś jeszcze popracować?',
        'q022' => 'Weź kartkę i długopis, poza oczami partnerom wypisz jego ulubione trzy tematy do rozmów. Niech druga osoba je wymieni. Jeśli zgadłeś minimum dwa twój partner w ciągu tygodnia zaplanuje i zorganizuje randkę, jeśli nie zgadłeś, zaplanuj ją.',
        'q023' => 'Czy jest rzecz, którą chciałbyś wprowadzić do łóżka, ale nie robisz jej w obawie przed reakcją drugiej strony? Jeśli tak, to jaką?',
        'q024' => 'Jak dużą rolę pełni dla Ciebie wygląd w twoim pożądaniu? Jakie są aspekty fizyczne, które je podsycają?',
        'q025' => 'Co sprawia lub mogłoby sprawić, że podczas wstępnej i seksu czułbyś się komfortowo i swobodnie? Co mógłby zrobić dla Ciebie twój partner?',
        'q026' => 'Jakie znaczenie ma dla Ciebie zdanie otoczenia o twoim związku? Czy są jakieś rady związkowe innych, do których próbowałeś się stosować?',
        'q027' => 'Jakie cechy sprawiają, że postrzegasz osobę za wartościową? Które z tych cech posiada twój partner?',
        'q028' => 'Co sprawia, że czujesz się zmotywowany? W jaki sposób twój partner wpływa na twoją motywację?',
        'q029' => 'Co według Ciebie potrafi uszczęśliwić twojego partnera? Jaki jest przepis na jego satysfakcję?',
        'q030' => 'Co myślisz o białym kłamstwie? Czy według Ciebie kłamstwo z intencją zadowolenia drugiej osoby jest dopuszczalne, czy stawiasz na prawdę, nawet jeśli jest gorzka?',
        'q031' => 'Bywają rzeczy ważne i ważniejsze. Co jest najbardziej istotne z wymienionych czynników: jakość gry wstępnej, stosunku, czy orgazm?',
        'q032' => 'Gdybyś miał podać komuś receptę na udany i długi związek, co byś w niej zawarł?',
        'q033' => 'Czy są zachowania, których nie byłbyś w stanie zaakceptować w związku? Mogą to być też poglądy lub podejścia, z którymi się nie utożsamiasz.',
        'q034' => 'Które z podejść twoich rodziców jest godne naśladowania, a których nie chciałbyś powtarzać w związku?',
        'q035' => 'Jakie są zalety bycia w związku? Co daje Ci relacja, w której jesteś?',
        'q036' => 'Jakich przydatnych umiejętności nauczył Cię partner?',
        'q037' => 'Opisz definicję udanego seksu. Możesz uwzględnić grę wstępną i czas bezpośrednio po nim. Wymieńcie wspólne wnioski i różnice, które widzicie w waszych definicjach.',
        'q038' => 'W jaki sposób udany seks wpływa na twoje samopoczucie? Co daje Ci zbliżenie, pomijając przyjemność podczas stosunku?',
        'q039' => 'Co wyróżnia twojego partnera na tle innych osób? Jakie są jego pozytywne, niepowtarzalne cechy?',
        'q040' => 'Co pozwala Ci uspokoić się po kłótni? Chwila dla siebie, rozmowa czy coś innego? Opisz, czego potrzebujesz, by poczuć się lepiej.',
        'q041' => 'Masz już plany lub zobowiązania, a twój partner prosi Cię o pomoc w sprawie wymagającej poświęcenia sporej ilości czasu. Co robisz?',
        'q042' => 'W jaki sposób najłatwiej podjąć Ci ważną decyzję? Potrzebujesz czasu, aby przeanalizować wszystkie za i przeciw, czy wolisz spontanicznie zadecydować?',
        'q043' => 'W jaki sposób rozplanowujesz czas? Wypełniasz jak najwięcej zadań, żeby mieć więcej czasu na odpoczynek, czy wolisz wykonywać je wolniej przy mniejszym nakładzie pracy?',
        'q044' => 'Co bardziej dodaje Ci energii: pełny wrażeń wieczór ze znajomymi, czy samotny relaks np. przy filmie lub książce?',
        'q045' => 'Przytocz scenę z filmu lub książki, która mogłaby stanowić dla Ciebie erotyczną inspirację. Które elementy chciałbyś wykorzystać?',
        'q046' => 'W której czynności seksualnej Twój partner pozostanie mistrzem? Jeśli pytanie was nie dotyczy, wymień cechy uroku osobistego partnera, które Cię uwiodły.',
        'q047' => 'Co wywołałoby u Ciebie większy entuzjazm: partner czekający na Ciebie w skąpym stroju z obietnicą pikantnego wieczoru, czy przygotowana kąpiel, masaż i subtelna, czuła noc?',
        'q048' => 'Opisz dokładnie, gdzie i w jaki sposób chciałbyś być dotykany, całowany w ramach gry wstępnej.',
        'q049' => 'Co sądzisz o otwartych związkach? Czy relacja wykraczająca poza dwie osoby może być udana?',
        'q050' => 'Jakie są silne strony waszego związku? Co sprawia, że można określić waszą relację jako niepowtarzalną?',
        'q051' => 'Wybierz zdanie opisujące najbardziej twoje odczucia w związku. Możesz je zmodyfikować lub rozwinąć.',
        'q052' => 'Na jakie odstępstwa decyduje się Twój partner, by sprawić Ci radość? W jaki sposób ty dostosowujesz się do partnera?',
        'q053' => 'W który sposób najczęściej okazujesz uczucie swojemu partnerowi?',
        'q054' => 'Jaki jest Twój stosunek do seksu oralnego? Czy jest to dla Ciebie ważna część życia seksualnego, czy może zupełnie niepotrzebna?',
        'q055' => 'Jakbyś zareagował na kilkudniowy wyjazd partnera w pojedynkę w ramach relaksu?',
        'q056' => 'Gdybyś cofnął się w czasie o 5 lat, jakie porady związkowe dałbyś młodszej wersji siebie?',
        'q057' => 'Jakbyś zareagował, gdyby twój najlepszy przyjaciel, rodzeństwo lub dziecko przedstawiło Ci swojego partnera, który byłby twoim lustrzanym odbiciem?',
        'q058' => 'Jakie zachowania partnera są dla Ciebie oznaką głębokiego zaangażowania?',
        'q059' => 'Jaki wpływ według Ciebie mają social media na dzisiejsze relacje? Jest on raczej pozytywny, czy negatywny?',
        'q060' => 'Co sądzisz o oglądaniu filmów pornograficznych w relacji? Czy mogą stanowić formę inspiracji lub przydatnego narzędzia?',
        'q061' => 'Jak reagujesz, gdy widzisz, że twój partner wzbudził zainteresowanie u innej osoby? Wprawia Cię to w podenerwowanie, czy raczej ogarnia Cię duma?',
        'q062' => 'Jak często i z jakich powodów zdarza Ci się pochwalić swojego partnera?',
        'q063' => 'Na co w pierwszej kolejności przeznaczyłbyś wspólnie pozyskany milion złotych?',
        'q064' => 'Gdybyś nie miał ograniczeń finansowych, jakie wakacje i w które miejsce chciałbyś się wybrać?',
        'q065' => 'Jaki zawód chciałbyś wykonywać, gdybyś wiedział, że pozostaniesz w nim całe życie?',
        'q066' => 'Jakbyś wykorzystał swoje trzy życzenia? (Nieskończona liczba życzeń nie wchodzi w grę)',
        'q067' => 'Które z wymienionych miejsc jest najbliższe twoim marzeniom? Czego jeszcze nie mogłoby zabraknąć w twoim wymarzonym domu?',
        'q068' => 'Gdyby wasze życie zostało obsadzone w filmie, jaki byłby jego gatunek i jaki nadałbyś mu tytuł?',
        'q069' => 'Co stanowi dla Ciebie najlepsze wsparcie, kiedy borykasz się problemem? Jaką formę ty dajesz najczęściej partnerowi?',
        'q070' => 'Które z poniższych rzeczy stanowi dla Ciebie największe i najbardziej stresujące wyzwanie?',
        'q071' => 'Co wychodzi najlepiej, gdy łączycie siły z partnerem?',
        'q072' => 'Co myśliw o przyjaźni z płcią, która jest w kręgu naszych zainteresowań? Czy zaakceptowałbyś przyjaźń swojego partnera?',
        'q073' => 'Które zachowanie, podejście u ludzi zawsze będzie Cię razić i jest w Twoich oczach niewybaczalne?',
        'q074' => 'Jakimi złotymi zasadami kierujesz się w życiu? Które wartości są dla Ciebie najważniejsze?',
        'q075' => 'Jaka czynność podczas dnia jest dla Ciebie nieodłączną częścią rutyny, z której nie byłbyś w stanie zrezygnować?',
        'q076' => 'Co jest największą oznaką dojrzałości w związku?',
        'q077' => 'Czy istnieje lub istniała rzecz, której wstydziłbyś się zrobić przy swoim partnerze? Jeśli tak, jaka?',
        'q078' => 'Jaka pora dnia i okoliczności są największym sprzymierzeńcem seksu?',
        'q079' => 'Czy możesz otwarcie porozmawiać z partnerem o trudnych emocjach? Czy są między wami tematy tabu?',
        'q080' => 'Która reakcja byłaby Ci najbliższa, gdyby partner chciał podzielić się z Tobą czymś, co przeszkadza mu w waszej relacji?',
        'q081' => 'Co najczęściej robisz, gdy nie odpowiada Ci zachowanie partnera?',
        'q082' => 'Partner opowiada Ci o swoim marzeniu, które chciałby spełnić, jednak jest ono trudne w realizacji. Jak zareagujesz?',
        'q083' => 'Na jaką czynność wiecznie brakuje Ci czasu?',
        'q084' => 'Jaką rolę gra dla Ciebie przeszłość partnera? Czy wpływa to na Twoje postrzeganie go w chwili obecnej?',
        'q085' => 'Miłość, przyjaźń, czy namiętność – co dominuje w waszym związku? Który z tych składników uważasz za najważniejszy?',
        'q086' => 'Jaka potrawa działa na Ciebie jak afrodyzjak?',
        'q087' => 'Z której swojej części ciała jesteś najbardziej dumny? Która część ciała partnera jest idealna?',
        'q088' => 'Czy gesty, jak pocałunek w rękę czy otworzenie drzwi są dla Ciebie oznaką kultury i szacunku?',
        'q089' => 'Kto powinien płacić na randce? Czy uważasz, że płacenie pół na pół jest sprawiedliwe?',
        'q090' => 'Do jakich obowiązków najciężej Ci się zebrać? Jaka czynność wymaga od Ciebie największych pokładów energii?',
        'q091' => 'Widzisz bardzo atrakcyjną osobę przechodzącą koło Ciebie i partnera. Co robisz?',
        'q092' => 'Czy byłbyś zadowolony, gdyby twój partner traktował Cię dokładnie tak, jak ty traktujesz jego?',
        'q093' => 'Jak często zdarza wam się spędzać czas przeznaczony tylko dla waszej dwójki bez smartfona, telewizji i komputera?',
        'q094' => 'Na jakie sposoby wykazujesz inicjatywę w związku? Jak wyraża ją Twój partner?',
        'q095' => 'Jak postrzegasz intercyzę? Czy świadczy ona o rozsądku, czy jest raczej oznaką braku zaufania?',
        'q096' => 'Co myślisz o związkach na odległość? Byłbyś w stanie trwać w takim związku?',
        'q097' => 'Czy powinno się dzielić wszystkim z partnerem? Czy w związku powinno być miejsce na małe sekrety?',
        'q098' => 'Czy założenie rodziny w młodym wieku jest korzystne w dzisiejszym świecie?',
        'q099' => 'Jaka jest Twoja największa obawa dotycząca związku?',
        'q100' => 'Które z poniższych rzeczy chciałbyś wprowadzić do związku? (Przestrzeń, bliskość emocjonalna, czas, bliskość fizyczna).',
    ];

    public function up(): void
    {
        $total = DB::table('questions')->where('type', 'session')->where('locale', 'pl')->count();

        if ($total === 0) {
            // Fresh database — nothing seeded yet.
            return;
        }

        // Checked before anything is written: if a body appears twice, the text
        // cannot say which row is meant, and the unique index would stop us
        // mid-way with a message about an index rather than about the content.
        $duplicated = DB::table('questions')
            ->where('type', 'session')->where('locale', 'pl')
            ->whereIn('body', array_values(self::MAP))
            ->select('body')
            ->groupBy('body')
            ->havingRaw('count(*) > 1')
            ->pluck('body');

        if ($duplicated->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Session deck backfill: %d entries appear more than once, so the text cannot identify a row. '
                .'First: %s',
                $duplicated->count(),
                mb_strimwidth((string) $duplicated->first(), 0, 70, '…'),
            ));
        }

        $matched = 0;

        foreach (self::MAP as $seedKey => $body) {
            if (DB::table('questions')->where('seed_key', $seedKey)->exists()) {
                // Already stamped: a re-run, not a second identity.
                $matched++;

                continue;
            }

            $matched += DB::table('questions')
                ->where('type', 'session')->where('locale', 'pl')
                ->where('body', $body)
                ->whereNull('seed_key')
                ->update(['seed_key' => $seedKey]);
        }

        if ($matched !== count(self::MAP)) {
            throw new RuntimeException(sprintf(
                'Session deck backfill matched %d of %d rows by body. The database no longer holds the '
                .'text this migration was frozen against — stop and compare before going further.',
                $matched,
                count(self::MAP),
            ));
        }

        // The count above says the file was fully matched; this says the POOL was.
        // A row nobody knew about would otherwise survive without a key, invisible
        // to a seeder that only ever looks things up by one — and stay that way.
        $unkeyed = DB::table('questions')
            ->where('type', 'session')->where('locale', 'pl')
            ->whereNull('seed_key')
            ->count();

        if ($unkeyed > 0) {
            throw new RuntimeException(sprintf(
                'Session deck backfill left %d rows without a seed_key. They are not in the frozen map, so '
                .'something put them there outside the seed — look before going further.',
                $unkeyed,
            ));
        }
    }

    public function down(): void
    {
        DB::table('questions')->whereIn('seed_key', array_keys(self::MAP))->update(['seed_key' => null]);
    }
};

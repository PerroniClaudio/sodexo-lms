<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;

class BuildLearningQuizPdfPayload
{
    /**
     * @return array{
     *     course: Course,
     *     module: Module,
     *     answerOptionLabels: array<int, string>,
     *     userSheets: Collection<int, array{
     *         user: User,
     *         qrCodeContent: string,
     *         qrCodeDataUri: string,
     *         questionNumbers: Collection<int, int>
     *     }>
     * }
     */
    public function __invoke(Course $course, Module $module): array
    {
        $module->loadMissing([
            'quizQuestions' => fn ($query) => $query->orderBy('id'),
        ]);

        $questionNumbers = $module->quizQuestions->isEmpty()
            ? collect()
            : collect(range(1, $module->quizQuestions->count()));

        return [
            'course' => $course,
            'module' => $module,
            'answerOptionLabels' => ['A', 'B', 'C', 'D'],
            'userSheets' => $course->users
                ->sortBy(fn (User $user): string => mb_strtolower(trim($user->surname.' '.$user->name)))
                ->values()
                ->map(function (User $user) use ($course, $module, $questionNumbers): array {
                    $qrCodeContent = base64_encode(implode('*', [
                        $course->getKey(),
                        $module->getKey(),
                        $user->getAuthIdentifier(),
                    ]));
                    $qrCodeSvgMarkup = $this->qrCodeWriter()->writeString($qrCodeContent);
                    $qrCodeSvg = trim(substr($qrCodeSvgMarkup, strpos($qrCodeSvgMarkup, "\n") + 1));

                    return [
                        'user' => $user,
                        'qrCodeContent' => $qrCodeContent,
                        'qrCodeDataUri' => 'data:image/svg+xml;base64,'.base64_encode($qrCodeSvg),
                        'questionNumbers' => $questionNumbers,
                    ];
                }),
        ];
    }

    private function qrCodeWriter(): Writer
    {
        return new Writer(
            new ImageRenderer(
                new RendererStyle(60, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))),
                new SvgImageBackEnd
            )
        );
    }
}

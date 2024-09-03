<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Image\Helper;

use App\Image\Loader\LoaderRegistry;
use Twig\Environment;

class ImageHelper
{
    /**
     * @var float
     */
    private const DEFAULT_ASPECT_RATIO = 4 / 3;

    /**
     * @var float[]|int[]
     */
    private const DEFAULT_PIXEL_DENSITIES = [0.25, 0.5, 1, 2];

    /**
     * @var int[]
     */
    private const DEFAULT_BREAKPOINTS = [750, 1080, 1366, 1920];

    /**
     * @var int[]
     */
    private const EVERY_BREAKPOINT = [
        320, 654, 768, 1024, 1366, 1600, 1920, 2048, 2560, 3440, 3840, 4096,
    ];

    /**
     * @var int
     */
    private const DEFAULT_FLUID_WIDTH = 800;

    /**
     * @var int
     */
    private const DEFAULT_FIXED_WIDTH = 800;

    private readonly array $defaultParams;

    public function __construct(
        private readonly LoaderRegistry $loaderRegistry,
        private readonly Environment $twig,
    ) {
        $this->defaultParams = [
            'path' => null,
            'priority' => false,
            'placeholderPriority' => null,
            'placeholder' => 'blur',
            'loader' => null,
            'loaderOptions' => [],
            'width' => null,
            'height' => null,
            'originalWidth' => null,
            'originalHeight' => null,
            'originalFormat' => null,
            'aspectRatio' => null,
            'attr' => [],
            'wrapperAttr' => [],
            'placeholderAttr' => [],
            'layout' => 'fluid',
            'objectFit' => 'cover',
            'objectFitPosition' => '50% 50%',
            'sizes' => [],
            'srcSet' => [],
            'formats' => ['webp'],
            'breakpoints' => null,
            'outputPixelDensities' => null,
        ];
    }

    public function image(array $params, ?Environment $twig = null): string
    {
        $loader = $this->loaderRegistry->getLoader($params);

        $loaderDefaultParams = $loader->getDefaultParams(array_merge(
            $this->defaultParams,
            $params
        ));
        $params = array_merge(
            $this->defaultParams,
            $loaderDefaultParams,
            $params
        );

        if (!isset($params['placeholderObjectFit'])) {
            $params['placeholderObjectFit'] = $params['objectFit'];
        }

        if (!isset($params['placeholderObjectFitPosition'])) {
            $params['placeholderObjectFitPosition'] = $params['objectFitPosition'];
        }

        [
            'priority' => $priority,
            'placeholderPriority' => $placeholderPriority,
            'placeholder' => $placeholder,
            'attr' => $attr,
            'layout' => $layout,
            'wrapperAttr' => $wrapperAttr,
            'placeholderAttr' => $placeholderAttr,
            'placeholderObjectFit' => $placeholderObjectFit,
            'placeholderObjectFitPosition' => $placeholderObjectFitPosition,
            'objectFit' => $objectFit,
            'objectFitPosition' => $objectFitPosition,
        ] = $params;

        if ('jpeg' === $params['originalFormat']) {
            $params['originalFormat'] = 'jpg';
        }

        $attr = $this->denormalizeAttr($attr);
        $wrapperAttr = $this->denormalizeAttr($wrapperAttr);
        $placeholderAttr = array_merge(
            $attr,
            $this->denormalizeAttr($placeholderAttr)
        );

        $attr['style'] ??= [];
        if ($objectFit) {
            $attr['style']['object-fit'] = $objectFit;
        }

        if ($objectFitPosition) {
            $attr['style']['object-position'] = $objectFitPosition;
        }

        $placeholderTemplate = null;
        if ('blur' === $placeholder) {
            $placeholderTemplate = $this->generateLowResImageSource($params);

            $placeholderAttr['style'] ??= [];
            if ($placeholderObjectFit) {
                $placeholderAttr['style']['object-fit'] = $placeholderObjectFit;
            }

            if ($placeholderObjectFitPosition) {
                $placeholderAttr['style']['object-position'] = $placeholderObjectFitPosition;
            }

            $placeholderTemplate['attr'] = $this->normalizeAttr($placeholderAttr);
        }

        $data = $this->getImageData($params);
        $image = $data['images']['fallback'];
        $image['attr'] = $attr;

        $templateOptions = [
            'layout' => $layout,
            'loading' => $priority ? 'eager' : 'lazy',
            'loadingPlaceholder' => ($placeholderPriority ?? $priority) ? 'eager' : 'lazy',
            'width' => $data['width'],
            'height' => $data['height'],
            'wrapper' => [
                'attr' => $this->normalizeAttr($wrapperAttr),
            ],
            'placeholder' => $placeholderTemplate,
            'image' => $data['images']['fallback'],
            'sources' => $data['images']['sources'],
            'attr' => $this->normalizeAttr($attr),
        ];

        return ($twig ?? $this->twig)->render('components/image.html.twig', $templateOptions);
    }

    private function normalizeAttr(array $attr): array
    {
        $normalizedAttr = [];
        foreach ($attr as $key => $value) {
            if ('style' === $key && \is_array($value)) {
                // ['max-width' => '400px', 'max-height' => '200px'] to max-width: 400px; max-height: 200px
                $cssValues = [];
                foreach ($value as $propertyName => $propertyValue) {
                    if (is_numeric($propertyName)) {
                        continue;
                    }

                    $cssValues[] = \sprintf('%s: %s', $propertyName, $propertyValue);
                }

                $value = implode('; ', $cssValues);
            } elseif ('class' === $key && \is_array($value)) {
                // ["foo", "bar"] to "foo bar"
                $value = implode(' ', array_unique($value));
            }

            if (null === $value || '' === trim((string) $value)) {
                continue;
            }

            $normalizedAttr[$key] = trim((string) $value);
        }

        return $normalizedAttr;
    }

    private function denormalizeAttr(array $attr): array
    {
        $denormalizedAttr = [];
        foreach ($attr as $key => $value) {
            if ('style' === $key && \is_string($value)) {
                // "max-width: 400px;max-height: 200px" to ['max-width' => '400px', 'max-height' => '200px']
                $value = explode(';', $value);

                $styleValue = [];
                foreach ($value as $style) {
                    $cssValueParts = explode(':', $style);
                    if (2 !== \count($cssValueParts)) {
                        continue;
                    }

                    [$propertyName, $propertyValue] = $cssValueParts;
                    $styleValue[$propertyName] = $propertyValue;
                }

                $value = $styleValue;
            } elseif ('class' === $key && \is_string($value)) {
                // "foo bar" to ["foo", "bar"]
                $value = explode(' ', $value);
            }

            if (\is_array($value)) {
                $value = array_filter(array_map('trim', $value));
            }

            if ([] === $value) {
                continue;
            }

            $denormalizedAttr[$key] = $value;
        }

        return $denormalizedAttr;
    }

    private function calculateImageSizes(array $params): array
    {
        [
            'layout' => $layout,
        ] = $params;

        if ('fixed' === $layout) {
            return $this->getFixedImageSizes($params);
        }

        if ('fluid' === $layout) {
            return $this->getResponsiveImageSizes($params);
        }

        if ('fullWidth' === $layout) {
            $params['breakpoints'] ??= self::DEFAULT_BREAKPOINTS;

            return $this->getResponsiveImageSizes($params);
        }

        return [];
    }

    private function getImageData(array $params): array
    {
        [
            'layout' => $layout,
            'breakpoints' => $breakpoints,
        ] = $params;

        if (0 === \count($breakpoints ?? []) && 'fullWidth' === $layout) {
            $breakpoints = self::EVERY_BREAKPOINT;
        }

        return $this->generateImageData(array_merge($params, [
            'breakpoints' => $breakpoints,
        ]));
    }

    private function generateImageData(array $params): array
    {
        $params = $this->getDefaultDimensions($params);

        [
            'width' => $width,
            'height' => $height,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'originalFormat' => $originalFormat,
            'layout' => $layout,
            'formats' => $formats,
            'loaderOptions' => $loaderOptions,
            'sizes' => $sizes,
        ] = $params;

        if (!$originalWidth && !$originalHeight) {
            $originalWidth = $width;
            $originalHeight = $height;
        }

        $imageSizes = $this->calculateImageSizes(array_merge($params, [
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
        ]));

        if (!$sizes) {
            $sizes = $this->getSizes(array_merge($params, [
                'width' => $imageSizes['presentationWidth'],
            ]));
        }

        $data = [
            'fallback' => null,
            'sources' => [],
        ];

        if (null !== $originalFormat) {
            $formats[] = $originalFormat;
        }

        foreach ($formats as $format) {
            $images = array_map(fn (int $size) => $this->generateImageSource(array_merge($params, [
                'width' => $size,
                'height' => round($size / $imageSizes['aspectRatio']),
                'format' => $format,
                'loaderOptions' => array_merge($loaderOptions, [
                    'fit' => $params['fit'] ?? null,
                    'quality' => 80,
                ]),
            ])
            ), $imageSizes['sizes']);

            if (\in_array($format, ['jpg', 'png'], true)) {
                $fallback = current(array_filter($images, static fn (array $image) => $image['width'] === $imageSizes['unscaledWidth']))
                    ?: current($images);

                if ($fallback) {
                    $data['fallback'] = [
                        'src' => $fallback['src'],
                        'srcSet' => $this->getSrcSet($images),
                        'sizes' => $sizes,
                    ];
                }
            }

            $data['sources'][] = [
                'srcSet' => $this->getSrcSet($images),
                'sizes' => $sizes,
                'mimeType' => \sprintf('image/%s', $format),
            ];
        }

        $imageProps = [
            'images' => $data,
        ];

        switch ($layout) {
            case 'fixed':
                $imageProps['width'] = $imageSizes['presentationWidth'];
                $imageProps['height'] = $imageSizes['presentationHeight'];
                break;
            case 'fullWidth':
                $imageProps['width'] = 1;
                $imageProps['height'] = 1 / $imageSizes['aspectRatio'];
                break;
            case 'fluid':
                $imageProps['width'] = ($width ?: $imageSizes['presentationWidth']) ?: 1;
                $imageProps['height'] = ($imageProps['width'] ?: 1) / $imageSizes['aspectRatio'];
                break;
        }

        return $imageProps;
    }

    private function getSrcSet(array $images): array
    {
        return array_map(static fn (array $image) => \sprintf(
            '%s %dw',
            $image['src'],
            $image['width'],
        ), $images);
    }

    private function generateLowResImageSource(array $params): array
    {
        $params = $this->getDefaultDimensions($params);
        [
            'originalFormat' => $originalFormat,
            'aspectRatio' => $aspectRatio,
            'loaderOptions' => $loaderOptions,
        ] = $params;

        return $this->generateImageSource(array_merge($params, [
            'format' => $originalFormat,
            'width' => 20,
            'height' => round(20 * $aspectRatio),
            'loaderOptions' => array_merge($loaderOptions, [
                'fit' => $params['fit'] ?? null,
                'quality' => 20,
            ]),
        ]));
    }

    private function generateImageSource(array $params): array
    {
        $loader = $this->loaderRegistry->getLoader($params);

        [
            'width' => $width,
            'height' => $height,
            'format' => $format,
        ] = $params;

        return [
            'width' => $width,
            'height' => $height,
            'src' => $loader->getUrl($params),
            'format' => $format,
        ];
    }

    private function getSizes(array $params): array
    {
        [
            'width' => $width,
            'layout' => $layout,
        ] = $params;

        $sizes = [];
        if ('fluid' === $layout) {
            // If screen is wider than the max size, image width is the max size,
            // otherwise it's the width of the screen
            $sizes = [
                \sprintf('(min-width: %dpx) %dpx', $width, $width),
                '100vw',
            ];
        } elseif ('fixed' === $layout) {
            // Image is always the same width, whatever the size of the screen
            $sizes = [
                \sprintf('%dpx', $width),
            ];
        } elseif ('fullWidth' === $layout) {
            $sizes = [
                '100vw',
            ];
        }

        return $sizes;
    }

    private function getResponsiveImageSizes(array $params): array
    {
        $params['fit'] ??= 'cover';
        $params['outputPixelDensities'] ??= self::DEFAULT_PIXEL_DENSITIES;

        [
            'width' => $width,
            'height' => $height,
            'fit' => $fit,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'layout' => $layout,
            'breakpoints' => $breakpoints,
            'outputPixelDensities' => $outputPixelDensities,
        ] = $params;

        $aspectRatio = $originalWidth / $originalHeight;

        // Sort, dedupe and ensure there's a 1
        $densities = array_unique(array_merge($outputPixelDensities, [1]));
        sort($densities, \SORT_NUMERIC);

        // If both are provided then we need to check the fit
        if ($width && $height) {
            $calculated = $this->getDimensionsAndAspectRatio([
                'width' => $originalWidth,
                'height' => $originalHeight,
            ], [
                'width' => $width,
                'height' => $height,
                'fit' => $fit,
            ]);

            [
                'width' => $width,
                'height' => $height,
                'aspectRatio' => $aspectRatio,
            ] = $calculated;
        }

        // Case 1: width of height were passed in, make sure it isn't larger than the actual image
        if ($width) {
            $width = min($width, $originalWidth);
        }

        if ($height) {
            $height = min($height, $originalHeight);
        }

        // Case 2: neither width or height were passed in, use default size
        if (!$width && !$height) {
            $width = min(self::DEFAULT_FLUID_WIDTH, $originalWidth);
            $height = $width / $aspectRatio;
        }

        // if it still hasn't been found, calculate width from the derived height.
        // TS isn't smart enough to realise the type for height has been narrowed here
        if (!$width) {
            $width = (int) $height * $aspectRatio;
        }

        $presentationWidth = $width;
        $isTopSizeOverriden = $originalWidth < $width
            || $originalHeight < $height;

        if ($isTopSizeOverriden) {
            $width = $originalWidth;
            $height = $originalHeight;
        }

        $width = round($width);

        if (($breakpoints ?? []) !== []) {
            $originalSizes = $breakpoints;
            $sizes = array_filter($breakpoints, static fn (int $breakpoint) => $breakpoint <= $originalWidth);
        } else {
            $sizes = array_map(static fn (float $density) => round($density * $width), $densities);
            $originalSizes = $sizes;
            $sizes = array_filter($sizes, static fn (float $size) => $size <= $originalWidth);
        }

        // If a larger breakpoint has been filtered-out, add the actual image width instead
        if (
            \count($sizes) < \count($originalSizes)
            && !\in_array((float) $originalWidth, $sizes, true)
        ) {
            $sizes[] = $originalWidth;
        }

        if ('fluid' === $layout && !\in_array((float) $width, $sizes, true)) {
            $sizes[] = $width;
        }

        sort($sizes, \SORT_NUMERIC);

        return [
            'sizes' => $sizes,
            'aspectRatio' => $aspectRatio,
            'presentationWidth' => $presentationWidth,
            'presentationHeight' => round($presentationWidth / $aspectRatio),
            'unscaledWidth' => $width,
        ];
    }

    private function getFixedImageSizes(array $params): array
    {
        $params = array_merge([
            'fit' => 'cover',
            'outputPixelDensities' => self::DEFAULT_PIXEL_DENSITIES,
        ], $params);

        [
            'width' => $width,
            'height' => $height,
            'fit' => $fit,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'outputPixelDensities' => $outputPixelDensities,
        ] = $params;

        $aspectRatio = $originalWidth / $originalHeight;

        // Sort, dedupe and ensure there's a 1
        $densities = array_unique(array_merge($outputPixelDensities ?? [], [1]));
        sort($densities, \SORT_NUMERIC);

        // If both are provided then we need to check the fit
        if ($width && $height) {
            $calculated = $this->getDimensionsAndAspectRatio([
                'width' => $originalWidth,
                'height' => $originalHeight,
            ], [
                'width' => $width,
                'height' => $height,
                'fit' => $fit,
            ]);

            [
                'width' => $width,
                'height' => $height,
                'aspectRatio' => $aspectRatio,
            ] = $calculated;
        }

        if (!$width) {
            if (!$height) {
                $width = self::DEFAULT_FIXED_WIDTH;
            } else {
                $width = round($height * $aspectRatio);
            }
        } elseif (!$height) {
            $height = round($width / $aspectRatio);
        }

        $presentationWidth = $width; // will use this for presentationWidth, don't want to lose it
        $isTopSizeOverriden = $originalWidth < $width
            || $originalHeight < $height;

        if ($isTopSizeOverriden) {
            // If the image is smaller than requested, warn the user that it's being processed as such
            // print out this message with the necessary information before we overwrite it for sizing
            $fixedDimension = $originalWidth < $width ? 'width' : 'height';

            if ('width' === $fixedDimension) {
                $width = $originalWidth;
                $height = round($width / $aspectRatio);
            } else {
                $height = $originalHeight;
                $width = round($height * $aspectRatio);
            }
        }

        // remove smaller densities because fixed images don't need them
        $sizes = array_filter($densities, static fn (float $density) => $density >= 1);
        $sizes = array_map(static fn (float $density) => $density * $width, $sizes);
        $sizes = array_filter($sizes, static fn (float $size) => $size <= $originalWidth);

        return [
            'sizes' => $sizes,
            'aspectRatio' => $aspectRatio,
            'presentationWidth' => $presentationWidth,
            'presentationHeight' => round($presentationWidth / $aspectRatio),
            'unscaledWidth' => $width,
        ];
    }

    private function getDimensionsAndAspectRatio(array $dimensions, array $options): array
    {
        $imageAspectRatio = $dimensions['width'] / $dimensions['height'];
        $width = $options['width'];
        $height = $options['height'];

        switch ($options['fit']) {
            case 'fill':
                $width = $options['width'] ?: $dimensions['width'];
                $height = $options['height'] ?: $dimensions['height'];
                break;
            case 'inside':
                $widthOption = $options['width'] ?: \PHP_INT_MAX;
                $heightOption = $options['height'] ?: \PHP_INT_MAX;

                $width = min($widthOption, round($heightOption * $imageAspectRatio));
                $height = min($heightOption, round($widthOption / $imageAspectRatio));
                break;
            case 'outside':
                $widthOption = $options['width'] ?: 0;
                $heightOption = $options['height'] ?: 0;

                $width = max($widthOption, round($heightOption * $imageAspectRatio));
                $height = max($heightOption, round($widthOption / $imageAspectRatio));
                break;
            default:
                if ($options['width'] && !$options['height']) {
                    $height = round($options['width'] / $imageAspectRatio);
                }

                if ($options['height'] && !$options['width']) {
                    $width = round($options['height'] * $imageAspectRatio);
                }

                break;
        }

        return [
            'width' => $width,
            'height' => $height,
            'aspectRatio' => $width / $height,
        ];
    }

    private function getDefaultDimensions(array $params): array
    {
        [
            'layout' => $layout,
            'width' => $width,
            'height' => $height,
            'aspectRatio' => $aspectRatio,
            'originalWidth' => $originalWidth,
            'originalHeight' => $originalHeight,
            'breakpoints' => $breakpoints,
        ] = $params;

        $breakpoints ??= [];

        if ($width && $height) {
            return array_merge($params, [
                'aspectRatio' => $width / $height,
            ]);
        }

        if ($originalWidth && $originalHeight && !$aspectRatio) {
            $aspectRatio = $originalWidth / $originalHeight;
        }

        if ('fullWidth' === $layout) {
            $width = $width ?: $originalWidth ?: end($breakpoints);
            $height = $height ?: round($width / ($aspectRatio ?? self::DEFAULT_ASPECT_RATIO));
        } else {
            if (!$width) {
                if ($height && $aspectRatio) {
                    $width = $height * $aspectRatio;
                } elseif ($originalWidth) {
                    $width = $originalWidth;
                } elseif ($height) {
                    $width = round($height / self::DEFAULT_ASPECT_RATIO);
                } else {
                    $width = self::DEFAULT_FIXED_WIDTH;
                }
            }

            if ($aspectRatio && !$height) {
                $height = round($width / $aspectRatio);
            } elseif (!$aspectRatio && $height) {
                $aspectRatio = $width / $height;
            } elseif (!$height && !$aspectRatio) {
                $aspectRatio = self::DEFAULT_ASPECT_RATIO;
                $height = round($width / $aspectRatio);
            }
        }

        return array_merge($params, [
            'width' => $width,
            'height' => $height,
            'aspectRatio' => $aspectRatio,
        ]);
    }

    private function stylesToStyle(array $styles): string
    {
        $stylesAsProperties = [];
        foreach ($styles as $property => $value) {
            $stylesAsProperties[] = \sprintf(
                '%s: %s',
                $property,
                $value
            );
        }

        return implode('; ', $stylesAsProperties);
    }
}

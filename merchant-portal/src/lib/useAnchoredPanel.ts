import { useEffectEvent, useLayoutEffect, type RefObject } from 'react';

const GAP = 4;
const EDGE = 8;

/**
 * Keeps an open panel (rendered with popover="manual") next to its trigger,
 * below it or above it when there is no room, and reports a press outside
 * both. The Popover API puts the panel in the top layer, so it shows above a
 * modal <dialog> and is never clipped by a scrolling container.
 */
export function useAnchoredPanel(
  open: boolean,
  anchorRef: RefObject<HTMLElement | null>,
  panelRef: RefObject<HTMLElement | null>,
  onOutsidePress: () => void,
) {
  const dismiss = useEffectEvent(onOutsidePress);

  useLayoutEffect(() => {
    const anchor = anchorRef.current;
    const panel = panelRef.current;
    if (!open || !anchor || !panel) return;

    panel.showPopover?.();

    const place = () => {
      const rect = anchor.getBoundingClientRect();
      panel.style.minWidth = `${rect.width}px`;
      const { offsetWidth: width, offsetHeight: height } = panel;
      const fitsBelow = rect.bottom + GAP + height <= window.innerHeight - EDGE;
      const fitsAbove = rect.top - GAP - height >= EDGE;
      const top =
        !fitsBelow && fitsAbove ? rect.top - GAP - height : rect.bottom + GAP;
      const left = Math.min(rect.left, window.innerWidth - width - EDGE);
      panel.style.top = `${top}px`;
      panel.style.left = `${Math.max(EDGE, left)}px`;
    };
    place();

    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (!anchor.contains(target) && !panel.contains(target)) dismiss();
    };
    const resizes =
      typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(place);
    resizes?.observe(panel);
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
    document.addEventListener('pointerdown', onPointerDown);

    return () => {
      resizes?.disconnect();
      window.removeEventListener('resize', place);
      window.removeEventListener('scroll', place, true);
      document.removeEventListener('pointerdown', onPointerDown);
      // A closed panel is unmounted, which takes it out of the top layer.
      if (panel.isConnected) panel.hidePopover?.();
    };
  }, [open, anchorRef, panelRef]);
}

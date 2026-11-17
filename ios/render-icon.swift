// Run: swift ios/render-icon.swift ios/FuelLog/Assets.xcassets/AppIcon.appiconset/Icon.png
import Foundation
import CoreGraphics
import ImageIO
import UniformTypeIdentifiers

let size = 1024
let context = CGContext(data: nil, width: size, height: size, bitsPerComponent: 8,
                        bytesPerRow: size * 4, space: CGColorSpaceCreateDeviceRGB(),
                        bitmapInfo: CGImageAlphaInfo.noneSkipLast.rawValue)!
let background = CGColor(red: 0.09, green: 0.06, blue: 0.13, alpha: 1)
let amber = CGColor(red: 1, green: 0.73, blue: 0.27, alpha: 1)
context.setFillColor(background)
context.fill(CGRect(x: 0, y: 0, width: size, height: size))
func rounded(_ rect: CGRect, _ radius: CGFloat, _ color: CGColor) {
    context.setFillColor(color)
    context.addPath(CGPath(roundedRect: rect, cornerWidth: radius, cornerHeight: radius, transform: nil))
    context.fillPath()
}
rounded(CGRect(x: 235, y: 205, width: 400, height: 615), 48, amber)
rounded(CGRect(x: 190, y: 160, width: 490, height: 65), 24, amber)
rounded(CGRect(x: 290, y: 555, width: 290, height: 200), 22, background)
context.setStrokeColor(amber); context.setLineWidth(45)
context.setLineCap(.round); context.setLineJoin(.round)
context.move(to: CGPoint(x: 635, y: 510))
context.addCurve(to: CGPoint(x: 770, y: 365), control1: CGPoint(x: 780, y: 510), control2: CGPoint(x: 655, y: 285))
context.addLine(to: CGPoint(x: 770, y: 655))
context.addLine(to: CGPoint(x: 690, y: 735))
context.strokePath()
// Verify artwork exists before exporting; this catches an empty render context.
let pixels = context.data!.assumingMemoryBound(to: UInt8.self)
let colorful = stride(from: 0, to: size * size * 4, by: 4).filter { pixels[$0] > 200 && pixels[$0 + 1] > 100 }.count
precondition(colorful > 100_000, "Missing amber artwork")
let image = context.makeImage()!
let destination = CGImageDestinationCreateWithURL(URL(fileURLWithPath: CommandLine.arguments[1]) as CFURL, UTType.png.identifier as CFString, 1, nil)!
CGImageDestinationAddImage(destination, image, nil)
precondition(CGImageDestinationFinalize(destination), "Could not write icon")
